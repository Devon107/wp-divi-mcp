<?php
/**
 * Python-semantics helpers for the validator port (validate.py).
 *
 * Values follow the PyJson model: a JSON object is a PHP array (string keys, kept
 * in insertion order), a JSON list is a PyList, scalars are PHP scalars. Strings
 * are UTF-8; slicing and positions count code points like Python's str.
 *
 * Pure PHP, no WordPress dependency, no mbstring requirement.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Static helpers mirroring the Python built-ins validate.py relies on.
 *
 * @internal Used by Validator and PyJson.
 */
final class Py {

	/**
	 * Characters Python's str.isspace() / regex \s accept, as a PCRE (/u) class body.
	 */
	const WS = '\x{9}-\x{d}\x{1c}-\x{20}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}';

	/**
	 * Python truthiness for PyJson values.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( $value instanceof PyList ) {
			return array() !== $value->items;
		}
		if ( is_string( $value ) ) {
			return '' !== $value;
		}
		if ( is_float( $value ) ) {
			return 0.0 !== $value; // NAN is truthy, as in Python.
		}
		return (bool) $value;
	}

	/**
	 * isinstance(value, dict).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_dict( $value ) {
		return is_array( $value );
	}

	/**
	 * dict.get(key): null when $node is not a dict or lacks the key.
	 *
	 * Where validate.py would raise on a non-dict (`"x".get(...)`), this returns null:
	 * the port treats such malformed values as absent instead of crashing.
	 *
	 * @param mixed  $node Value.
	 * @param string $key  Key.
	 * @return mixed
	 */
	public static function get( $node, $key ) {
		return is_array( $node ) && array_key_exists( $key, $node ) ? $node[ $key ] : null;
	}

	/**
	 * Walk dict keys with `(x.get(k) or {})` semantics; null when any step is missing.
	 *
	 * @param mixed  $node Value.
	 * @param string ...$keys Keys.
	 * @return mixed
	 */
	public static function dig( $node, ...$keys ) {
		foreach ( $keys as $key ) {
			$node = self::get( $node, $key );
			if ( null === $node ) {
				return null;
			}
		}
		return $node;
	}

	/**
	 * `key in value` for a dict (keys) or list (items, strict).
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Container.
	 * @return bool
	 */
	public static function has( $key, $value ) {
		if ( is_array( $value ) ) {
			return array_key_exists( $key, $value );
		}
		if ( $value instanceof PyList ) {
			return in_array( $key, $value->items, true );
		}
		return false;
	}

	/**
	 * Dict keys as strings (PHP turns "1" keys into ints).
	 *
	 * @param array $dict Dict.
	 * @return string[]
	 */
	public static function keys( array $dict ) {
		return array_map( 'strval', array_keys( $dict ) );
	}

	/**
	 * Items of `for x in (value or [])`: list items, dict keys, string characters.
	 * Truthy values Python cannot iterate (numbers, True) give nothing.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	public static function iter( $value ) {
		if ( $value instanceof PyList ) {
			return $value->items;
		}
		if ( is_array( $value ) ) {
			return self::keys( $value );
		}
		if ( is_string( $value ) ) {
			return self::chars( $value );
		}
		return array();
	}

	/**
	 * sorted() of strings (code point order).
	 *
	 * @param string[] $items Items.
	 * @return string[]
	 */
	public static function sorted( array $items ) {
		$items = array_values( array_map( 'strval', $items ) );
		usort( $items, 'strcmp' );
		return $items;
	}

	/**
	 * Code points of a UTF-8 string.
	 *
	 * @param string $s String.
	 * @return string[]
	 */
	public static function chars( $s ) {
		if ( '' === $s ) {
			return array();
		}
		$parts = preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY );
		return false === $parts ? str_split( $s ) : $parts;
	}

	/**
	 * Number of code points in a UTF-8 string (len()).
	 *
	 * @param string $s String.
	 * @return int
	 */
	public static function len( $s ) {
		return strlen( $s ) - (int) preg_match_all( '/[\x80-\xBF]/', $s );
	}

	/**
	 * s[:n] in code points.
	 *
	 * @param string $s String.
	 * @param int    $n Length.
	 * @return string
	 */
	public static function slice( $s, $n ) {
		if ( strlen( $s ) <= $n ) {
			return $s;
		}
		return preg_match( '/^.{0,' . (int) $n . '}/su', $s, $m ) ? $m[0] : substr( $s, 0, $n );
	}

	/**
	 * str.strip() with Python's whitespace set.
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function strip( $s ) {
		$out = preg_replace( '/^[' . self::WS . ']+|[' . self::WS . ']+$/u', '', $s );
		return null === $out ? trim( $s ) : $out;
	}

	/**
	 * str.lower().
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function lower( $s ) {
		if ( ! preg_match( '/[\x80-\xff]/', $s ) ) {
			return strtolower( $s );
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * Replace invalid UTF-8 sequences with U+FFFD (Python strings are always valid).
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function scrub( $s ) {
		if ( preg_match( '//u', $s ) ) {
			return $s;
		}
		$valid = '/([\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
			. '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|(.)/s';
		return (string) preg_replace_callback(
			$valid,
			static function ( $m ) {
				return isset( $m[2] ) ? "\xEF\xBF\xBD" : $m[1];
			},
			$s
		);
	}

	/**
	 * Python str(): strings as is, everything else as repr().
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function str( $value ) {
		return is_string( $value ) ? $value : self::repr( $value );
	}

	/**
	 * Python repr() of a PyJson value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function repr( $value ) {
		if ( is_string( $value ) ) {
			return self::repr_str( $value );
		}
		if ( $value instanceof PyList ) {
			return '[' . implode( ', ', array_map( array( self::class, 'repr' ), $value->items ) ) . ']';
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $k => $v ) {
				$parts[] = self::repr_str( (string) $k ) . ': ' . self::repr( $v );
			}
			return '{' . implode( ', ', $parts ) . '}';
		}
		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || null === $value ) {
			return D5::py_str( $value );
		}
		return is_object( $value ) ? '<' . get_class( $value ) . '>' : gettype( $value );
	}

	/**
	 * Python repr() of a str: quote choice and escapes as in CPython's unicode_repr.
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function repr_str( $s ) {
		$quote = ( false !== strpos( $s, "'" ) && false === strpos( $s, '"' ) ) ? '"' : "'";
		$out   = $quote;
		foreach ( self::chars( $s ) as $ch ) {
			if ( $ch === $quote || '\\' === $ch ) {
				$out .= '\\' . $ch;
				continue;
			}
			if ( "\t" === $ch ) {
				$out .= '\t';
				continue;
			}
			if ( "\n" === $ch ) {
				$out .= '\n';
				continue;
			}
			if ( "\r" === $ch ) {
				$out .= '\r';
				continue;
			}
			$cp = self::ord( $ch );
			if ( $cp < 0x20 || 0x7f === $cp ) {
				$out .= sprintf( '\x%02x', $cp );
			} elseif ( $cp < 0x7f ) {
				$out .= $ch;
			} elseif ( ! preg_match( '/^[\p{C}\p{Z}]$/u', $ch ) ) {
				$out .= $ch; // Printable.
			} elseif ( $cp <= 0xff ) {
				$out .= sprintf( '\x%02x', $cp );
			} elseif ( $cp <= 0xffff ) {
				$out .= sprintf( '\u%04x', $cp );
			} else {
				$out .= sprintf( '\U%08x', $cp );
			}
		}
		return $out . $quote;
	}

	/**
	 * Code point of one UTF-8 character.
	 *
	 * @param string $ch Character.
	 * @return int
	 */
	public static function ord( $ch ) {
		$b = ord( $ch[0] );
		if ( $b < 0x80 ) {
			return $b;
		}
		if ( $b < 0xE0 ) {
			return ( ( $b & 0x1F ) << 6 ) | ( ord( $ch[1] ) & 0x3F );
		}
		if ( $b < 0xF0 ) {
			return ( ( $b & 0x0F ) << 12 ) | ( ( ord( $ch[1] ) & 0x3F ) << 6 ) | ( ord( $ch[2] ) & 0x3F );
		}
		return ( ( $b & 0x07 ) << 18 ) | ( ( ord( $ch[1] ) & 0x3F ) << 12 ) | ( ( ord( $ch[2] ) & 0x3F ) << 6 ) | ( ord( $ch[3] ) & 0x3F );
	}

	/**
	 * UTF-8 encoding of a code point (surrogates become U+FFFD).
	 *
	 * @param int $cp Code point.
	 * @return string
	 */
	public static function chr( $cp ) {
		if ( $cp >= 0xD800 && $cp <= 0xDFFF ) {
			$cp = 0xFFFD;
		}
		if ( $cp < 0x80 ) {
			return chr( $cp );
		}
		if ( $cp < 0x800 ) {
			return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		if ( $cp < 0x10000 ) {
			return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
	}
}
