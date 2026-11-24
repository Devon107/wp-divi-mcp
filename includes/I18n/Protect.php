<?php
/**
 * Protected parts of translatable HTML: the bytes a translation must never touch.
 *
 * Protected parts are, in document order: `<script…>…</script>`, `<style…>…</style>`,
 * shortcodes `[name …]` / `[/name]`, HTML comments `<!-- … -->` and `<?php … ?>`. mask()
 * replaces each one with a numbered marker ⟦n⟧ (U+27E6, n, U+27E7; n from 1), so only the text
 * between tags is translated, and unmask() restores the originals byte for byte. Text that
 * already looks like a marker is itself protected, so a round trip is always exact.
 *
 * An unclosed script, style, comment or PHP block is protected up to the end of the text.
 *
 * Pure PHP, no WordPress dependency. Linear scan (no backtracking regex over long parts).
 *
 * @package WPDM
 */

namespace WPDM\I18n;

/**
 * Marker masking for html segments.
 */
final class Protect {

	const OPEN  = "\u{27E6}";
	const CLOSE = "\u{27E7}";

	/**
	 * Marker regex (bytes, no /u so invalid UTF-8 cannot make it fail).
	 */
	const MARKER_RE = '/\xE2\x9F\xA6(\d+)\xE2\x9F\xA7/';

	/**
	 * Replace the protected parts with ⟦1⟧…⟦n⟧.
	 *
	 * @param string $html HTML.
	 * @return array{text: string, parts: string[]}
	 */
	public static function mask( string $html ): array {
		$parts = array();
		$text  = '';
		$n     = strlen( $html );
		$pos   = 0;
		while ( $pos < $n ) {
			$at = $pos + strcspn( $html, "<[\xE2", $pos );
			if ( $at >= $n ) {
				$text .= substr( $html, $pos );
				break;
			}
			$end = self::part_end( $html, $at );
			if ( null === $end ) {
				$text .= substr( $html, $pos, $at + 1 - $pos );
				$pos   = $at + 1;
				continue;
			}
			$parts[] = substr( $html, $at, $end - $at );
			$text   .= substr( $html, $pos, $at - $pos ) . self::OPEN . count( $parts ) . self::CLOSE;
			$pos     = $end;
		}
		return array(
			'text'  => $text,
			'parts' => $parts,
		);
	}

	/**
	 * Restore the protected parts. Markers may be reordered, but each of ⟦1⟧…⟦n⟧ must appear
	 * exactly once and no other marker may appear.
	 *
	 * @param string   $text  Masked text.
	 * @param string[] $parts Parts from mask().
	 * @return string|null Null when a marker is missing, duplicated or unknown.
	 */
	public static function unmask( string $text, array $parts ): ?string {
		$parts = array_values( $parts );
		if ( false === preg_match_all( self::MARKER_RE, $text, $m ) ) {
			return null;
		}
		$seen = array();
		foreach ( $m[1] as $digits ) {
			$i = (int) $digits;
			if ( (string) $i !== $digits || $i < 1 || $i > count( $parts ) || isset( $seen[ $i ] ) ) {
				return null;
			}
			$seen[ $i ] = true;
		}
		if ( count( $seen ) !== count( $parts ) ) {
			return null;
		}
		return preg_replace_callback(
			self::MARKER_RE,
			static function ( $mm ) use ( $parts ) {
				return $parts[ (int) $mm[1] - 1 ];
			},
			$text
		);
	}

	/**
	 * Sequence of tag names, lowercased, closers prefixed with "/": `<div><p></p></div>` gives
	 * ['div', 'p', '/p', '/div']. Meant for masked text (comments and scripts are markers).
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function tags( string $html ): array {
		if ( ! preg_match_all( '/<(\/?)([A-Za-z][A-Za-z0-9:-]*+)[^>]*+>/', $html, $m, PREG_SET_ORDER ) ) {
			return array();
		}
		$out = array();
		foreach ( $m as $tag ) {
			$out[] = $tag[1] . strtolower( $tag[2] );
		}
		return $out;
	}

	/**
	 * End offset of the protected part starting at $at, or null when none starts there.
	 *
	 * @param string $s  Text.
	 * @param int    $at Offset of "<", "[" or a 0xE2 byte.
	 * @return int|null
	 */
	private static function part_end( $s, $at ) {
		$n = strlen( $s );
		if ( '[' === $s[ $at ] ) {
			return preg_match( '/\G\[\/?[A-Za-z_][\w-]*+(?=[\s\]\/])[^\[\]]*+\]/', $s, $m, 0, $at ) ? $at + strlen( $m[0] ) : null;
		}
		if ( "\xE2" === $s[ $at ] ) {
			return preg_match( '/\G\xE2\x9F\xA6\d+\xE2\x9F\xA7/', $s, $m, 0, $at ) ? $at + strlen( $m[0] ) : null;
		}
		if ( 0 === substr_compare( $s, '<!--', $at, 4 ) ) {
			return self::after( $s, '-->', $at + 4 );
		}
		if ( $at + 5 <= $n && 0 === substr_compare( $s, '<?php', $at, 5, true ) ) {
			return self::after( $s, '?>', $at + 5 );
		}
		foreach ( array( 'script', 'style' ) as $tag ) {
			$len = strlen( $tag );
			if ( $at + 1 + $len > $n || 0 !== substr_compare( $s, $tag, $at + 1, $len, true ) ) {
				continue;
			}
			$next = $at + 1 + $len < $n ? $s[ $at + 1 + $len ] : '';
			if ( '' !== $next && false === strpos( " \t\n\r\f/>", $next ) ) {
				continue;
			}
			$close = stripos( $s, '</' . $tag, $at + 1 + $len );
			return false === $close ? $n : self::after( $s, '>', $close );
		}
		return null;
	}

	/**
	 * Offset just past the first $needle at or after $from, or the text length when absent.
	 *
	 * @param string $s      Text.
	 * @param string $needle Needle.
	 * @param int    $from   Offset.
	 * @return int
	 */
	private static function after( $s, $needle, $from ) {
		$p = strpos( $s, $needle, $from );
		return false === $p ? strlen( $s ) : $p + strlen( $needle );
	}
}
