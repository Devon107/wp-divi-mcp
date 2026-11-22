<?php
/**
 * Finds Divi/WordPress block comments the way validate.py's BLOCK_RE does, without PCRE.
 *
 * validate.py scans markup with
 *
 *     <!--\s+(/)?wp:(ns/name|name)(\s+\{.*?\})?\s+(/)?-->      (re.S, Unicode \s)
 *
 * In PHP the lazy `\{.*?\}` hits pcre.backtrack_limit on large attributes (about
 * 1 MB of text, or ~500 KB full of "} "), and the whole page then fails to scan.
 * This scanner produces exactly the regex's matches (same leftmost, non-overlapping
 * finditer semantics) in linear time, independent of ini settings:
 *
 * - Every piece except the attribute group is deterministic: `\s+` is always
 *   followed by a non-space literal (`/`, `w`, `{`, `-`), the name character set
 *   excludes both `/` and spaces, and `(/)?` before `wp:` or `-->` can only match
 *   one way. So backtracking can only change where the lazy `.*?` stops.
 * - `.*?` (with re.S) stops at the first `}` at or after the opening brace + 1 that
 *   is followed by `\s+(/)?-->`, anywhere later in the content (it can run past
 *   other comments, as the regex does). If there is none, the optional group is
 *   skipped and the tag must close right after the name.
 * - Those "closing brace" positions are found once and reused, since match start
 *   positions only increase.
 *
 * Content must be valid UTF-8 (Validator scrubs it first).
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Block comment scanner.
 */
final class BlockScanner {

	/**
	 * Content.
	 *
	 * @var string
	 */
	private $s;

	/**
	 * Length in bytes.
	 *
	 * @var int
	 */
	private $n;

	/**
	 * Cached result of the last closing-brace search: [from, found position or -1].
	 *
	 * @var array{0: int, 1: int}
	 */
	private $brace = array( -1, -1 );

	/**
	 * @param string $s Content.
	 */
	private function __construct( $s ) {
		$this->s = $s;
		$this->n = strlen( $s );
	}

	/**
	 * All block comments in order.
	 *
	 * @param string $content Markup (valid UTF-8).
	 * @return array<int, array{start: int, end: int, closer: bool, name: string, raw: string|null, void: bool}>
	 *         raw is the attribute group as the regex captures it (leading whitespace included).
	 */
	public static function scan( string $content ): array {
		$sc  = new self( $content );
		$out = array();
		$pos = 0;
		while ( false !== ( $p = strpos( $content, '<!--', $pos ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$m = $sc->match_at( $p );
			if ( null === $m ) {
				$pos = $p + 1;
				continue;
			}
			$out[] = $m;
			$pos   = $m['end'];
		}
		return $out;
	}

	/**
	 * The regex match starting exactly at $p, or null.
	 *
	 * @param int $p Index of "<!--".
	 * @return array|null
	 */
	private function match_at( $p ) {
		$i = $this->skip_ws( $p + 4 );
		if ( $i === $p + 4 ) {
			return null;
		}
		$closer = $i < $this->n && '/' === $this->s[ $i ];
		if ( $closer ) {
			++$i;
		}
		if ( 'wp:' !== substr( $this->s, $i, 3 ) ) {
			return null;
		}
		$i += 3;

		$name_end = $this->segment( $i );
		if ( $name_end === $i ) {
			return null;
		}
		if ( $name_end < $this->n && '/' === $this->s[ $name_end ] ) {
			$second = $this->segment( $name_end + 1 );
			if ( $second > $name_end + 1 ) {
				$name_end = $second;
			}
		}
		$name = substr( $this->s, $i, $name_end - $i );

		// Optional attribute group: \s+ \{ .*? \}  (then the tail must follow the brace).
		$k = $this->skip_ws( $name_end );
		if ( $k > $name_end && $k < $this->n && '{' === $this->s[ $k ] ) {
			$e = $this->closing_brace( $k + 1 );
			if ( $e >= 0 ) {
				$tail = $this->tail( $e + 1 );
				return array(
					'start'  => $p,
					'end'    => $tail[0],
					'closer' => $closer,
					'name'   => $name,
					'raw'    => substr( $this->s, $name_end, $e + 1 - $name_end ),
					'void'   => $tail[1],
				);
			}
		}
		$tail = $this->tail( $name_end );
		if ( null === $tail ) {
			return null;
		}
		return array(
			'start'  => $p,
			'end'    => $tail[0],
			'closer' => $closer,
			'name'   => $name,
			'raw'    => null,
			'void'   => $tail[1],
		);
	}

	/**
	 * End of `[a-z][a-z0-9_-]*` starting at $i ($i when it does not match).
	 *
	 * @param int $i Index.
	 * @return int
	 */
	private function segment( $i ) {
		if ( $i >= $this->n || $this->s[ $i ] < 'a' || $this->s[ $i ] > 'z' ) {
			return $i;
		}
		return $i + 1 + strspn( $this->s, 'abcdefghijklmnopqrstuvwxyz0123456789_-', $i + 1 );
	}

	/**
	 * Match `\s+(/)?-->` at $i: [end, void] or null.
	 *
	 * @param int $i Index.
	 * @return array{0: int, 1: bool}|null
	 */
	private function tail( $i ) {
		$j = $this->skip_ws( $i );
		if ( $j === $i ) {
			return null;
		}
		$void = $j < $this->n && '/' === $this->s[ $j ];
		if ( $void ) {
			++$j;
		}
		if ( '-->' !== substr( $this->s, $j, 3 ) ) {
			return null;
		}
		return array( $j + 3, $void );
	}

	/**
	 * First `}` at or after $from that is followed by `\s+(/)?-->`; -1 when none.
	 * Queries come with non-decreasing $from, so one forward pass serves them all.
	 *
	 * @param int $from Index.
	 * @return int
	 */
	private function closing_brace( $from ) {
		list( $cached_from, $found ) = $this->brace;
		if ( $cached_from >= 0 && $from >= $cached_from && ( -1 === $found || $found >= $from ) ) {
			return $found;
		}
		$at = $from;
		while ( false !== ( $e = strpos( $this->s, '}', $at ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( null !== $this->tail( $e + 1 ) ) {
				$this->brace = array( $from, $e );
				return $e;
			}
			$at = $e + 1;
		}
		$this->brace = array( $from, -1 );
		return -1;
	}

	/**
	 * Skip Python whitespace (str.isspace) from $i.
	 *
	 * @param int $i Index.
	 * @return int
	 */
	private function skip_ws( $i ) {
		while ( $i < $this->n ) {
			$len = $this->ws_len( $i );
			if ( 0 === $len ) {
				break;
			}
			$i += $len;
		}
		return $i;
	}

	/**
	 * Byte length of the whitespace character at $i (0 when it is not whitespace).
	 * Same set as Py::WS: \t-\r, \x1c-\x20, U+0085, U+00A0, U+1680, U+2000-U+200A,
	 * U+2028, U+2029, U+202F, U+205F, U+3000.
	 *
	 * @param int $i Index.
	 * @return int
	 */
	private function ws_len( $i ) {
		$b = ord( $this->s[ $i ] );
		if ( ( $b >= 0x09 && $b <= 0x0d ) || ( $b >= 0x1c && $b <= 0x20 ) ) {
			return 1;
		}
		if ( $b < 0xC2 || $i + 1 >= $this->n ) {
			return 0;
		}
		$b1 = ord( $this->s[ $i + 1 ] );
		if ( 0xC2 === $b ) {
			return ( 0x85 === $b1 || 0xA0 === $b1 ) ? 2 : 0;
		}
		if ( $i + 2 >= $this->n ) {
			return 0;
		}
		$b2 = ord( $this->s[ $i + 2 ] );
		if ( 0xE1 === $b ) {
			return ( 0x9A === $b1 && 0x80 === $b2 ) ? 3 : 0;
		}
		if ( 0xE2 === $b ) {
			if ( 0x80 === $b1 ) {
				return ( $b2 <= 0x8A && $b2 >= 0x80 ) || 0xA8 === $b2 || 0xA9 === $b2 || 0xAF === $b2 ? 3 : 0;
			}
			return ( 0x81 === $b1 && 0x9F === $b2 ) ? 3 : 0;
		}
		if ( 0xE3 === $b ) {
			return ( 0x80 === $b1 && 0x80 === $b2 ) ? 3 : 0;
		}
		return 0;
	}
}
