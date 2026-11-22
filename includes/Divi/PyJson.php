<?php
/**
 * JSON decoder that behaves like Python's json.loads (CPython's C scanner).
 *
 * validate.py reports `attributes are not valid JSON (<exc>)` with Python's own
 * error text ("Expecting ',' delimiter: line 1 column 7 (char 6)"), so the port
 * needs the same accept/reject rules and the same messages and positions.
 * Objects decode to PHP arrays (string keys, insertion order, last duplicate
 * wins), lists to PyList. NaN, Infinity and -Infinity are accepted like Python.
 *
 * Known differences: lone UTF-16 surrogates decode to U+FFFD (PHP strings are
 * UTF-8); integers beyond PHP_INT_MAX decode to float; nesting deeper than
 * MAX_DEPTH is an error (Python raises RecursionError far deeper).
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Python-compatible JSON decoder.
 */
final class PyJson {

	const MAX_DEPTH = 512;

	/**
	 * Input bytes.
	 *
	 * @var string
	 */
	private $s;

	/**
	 * Input length in bytes.
	 *
	 * @var int
	 */
	private $n;

	/**
	 * @param string $s Input (valid UTF-8).
	 */
	private function __construct( $s ) {
		$this->s = $s;
		$this->n = strlen( $s );
	}

	/**
	 * json.loads(s).
	 *
	 * @param string $s JSON text (UTF-8).
	 * @return mixed
	 * @throws PyJsonError With Python's message.
	 */
	public static function loads( $s ) {
		$p = new self( $s );
		if ( 0 === strncmp( $s, "\xEF\xBB\xBF", 3 ) ) {
			throw $p->error( 'Unexpected UTF-8 BOM (decode using utf-8-sig)', 0 );
		}
		list( $value, $end ) = $p->scan( $p->ws( 0 ), 0 );
		$end                 = $p->ws( $end );
		if ( $end !== $p->n ) {
			throw $p->error( 'Extra data', $end );
		}
		return $value;
	}

	/**
	 * Skip JSON whitespace.
	 *
	 * @param int $i Byte index.
	 * @return int
	 */
	private function ws( $i ) {
		return $i + strspn( $this->s, " \t\n\r", $i );
	}

	/**
	 * Build an error with Python's "msg: line L column C (char P)" text.
	 *
	 * @param string $msg Message.
	 * @param int    $pos Byte index.
	 * @return PyJsonError
	 */
	private function error( $msg, $pos ) {
		$prefix = substr( $this->s, 0, $pos );
		$char   = Py::len( $prefix );
		$nl     = strrpos( $prefix, "\n" );
		$line   = substr_count( $prefix, "\n" ) + 1;
		$col    = false === $nl ? $char + 1 : $char - Py::len( substr( $prefix, 0, $nl ) );
		return new PyJsonError( sprintf( '%s: line %d column %d (char %d)', $msg, $line, $col, $char ) );
	}

	/**
	 * scan_once: one value at $i.
	 *
	 * @param int $i     Byte index.
	 * @param int $depth Nesting depth.
	 * @return array{0: mixed, 1: int} Value and end index.
	 * @throws PyJsonError When invalid.
	 */
	private function scan( $i, $depth ) {
		if ( $i >= $this->n ) {
			throw $this->error( 'Expecting value', $i );
		}
		$c = $this->s[ $i ];
		if ( '"' === $c ) {
			return $this->string( $i + 1 );
		}
		if ( '{' === $c || '[' === $c ) {
			if ( $depth >= self::MAX_DEPTH ) {
				throw $this->error( 'Maximum nesting depth exceeded', $i );
			}
			return '{' === $c ? $this->object( $i + 1, $depth + 1 ) : $this->array( $i + 1, $depth + 1 );
		}
		foreach ( array(
			'null'      => null,
			'true'      => true,
			'false'     => false,
			'NaN'       => NAN,
			'Infinity'  => INF,
			'-Infinity' => -INF,
		) as $word => $value ) {
			if ( 0 === substr_compare( $this->s, $word, $i, strlen( $word ) ) ) {
				return array( $value, $i + strlen( $word ) );
			}
		}
		if ( preg_match( '/\G-?(?:0|[1-9][0-9]*)(\.[0-9]+)?([eE][-+]?[0-9]+)?/', $this->s, $m, 0, $i ) ) {
			$num = $m[0];
			$end = $i + strlen( $num );
			if ( ( isset( $m[1] ) && '' !== $m[1] ) || isset( $m[2] ) ) {
				return array( (float) $num, $end );
			}
			$int = (int) $num;
			return array( (string) $int === $num || '-0' === $num ? $int : (float) $num, $end );
		}
		throw $this->error( 'Expecting value', $i );
	}

	/**
	 * scanstring: string body starting after the opening quote.
	 *
	 * @param int $end Index after the quote.
	 * @return array{0: string, 1: int}
	 * @throws PyJsonError When invalid.
	 */
	private function string( $end ) {
		$begin  = $end - 1;
		$chunks = '';
		$stop   = "\"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f";
		while ( true ) {
			$next = $end + strcspn( $this->s, $stop, $end );
			if ( $next >= $this->n ) {
				throw $this->error( 'Unterminated string starting at', $begin );
			}
			$c = $this->s[ $next ];
			if ( '"' !== $c && '\\' !== $c ) {
				throw $this->error( 'Invalid control character at', $next );
			}
			$chunks .= substr( $this->s, $end, $next - $end );
			++$next;
			if ( '"' === $c ) {
				return array( $chunks, $next );
			}
			if ( $next === $this->n ) {
				throw $this->error( 'Unterminated string starting at', $begin );
			}
			$c = $this->s[ $next ];
			if ( 'u' !== $c ) {
				$end = $next + 1;
				$map = array(
					'"'  => '"',
					'\\' => '\\',
					'/'  => '/',
					'b'  => "\x08",
					'f'  => "\x0c",
					'n'  => "\n",
					'r'  => "\r",
					't'  => "\t",
				);
				if ( ! isset( $map[ $c ] ) ) {
					throw $this->error( 'Invalid \\escape', $end - 2 );
				}
				$chunks .= $map[ $c ];
				continue;
			}
			++$next;
			$end = $next + 4;
			if ( $end >= $this->n ) {
				throw $this->error( 'Invalid \\uXXXX escape', $next - 1 );
			}
			$hex = substr( $this->s, $next, 4 );
			if ( ! preg_match( '/^[0-9a-fA-F]{4}$/', $hex ) ) {
				throw $this->error( 'Invalid \\uXXXX escape', $end - 5 );
			}
			$cp = hexdec( $hex );
			if ( $cp >= 0xD800 && $cp <= 0xDBFF && $end + 6 < $this->n
				&& '\\' === $this->s[ $end ] && 'u' === $this->s[ $end + 1 ] ) {
				$hex2 = substr( $this->s, $end + 2, 4 );
				if ( ! preg_match( '/^[0-9a-fA-F]{4}$/', $hex2 ) ) {
					throw $this->error( 'Invalid \\uXXXX escape', $end + 1 );
				}
				$cp2 = hexdec( $hex2 );
				if ( $cp2 >= 0xDC00 && $cp2 <= 0xDFFF ) {
					$cp   = 0x10000 + ( ( $cp - 0xD800 ) << 10 ) + ( $cp2 - 0xDC00 );
					$end += 6;
				}
			}
			$chunks .= Py::chr( $cp );
		}
	}

	/**
	 * Object body after '{'.
	 *
	 * @param int $i     Index after '{'.
	 * @param int $depth Depth.
	 * @return array{0: array, 1: int}
	 * @throws PyJsonError When invalid.
	 */
	private function object( $i, $depth ) {
		$out = array();
		$i   = $this->ws( $i );
		if ( $i < $this->n && '}' === $this->s[ $i ] ) {
			return array( $out, $i + 1 );
		}
		while ( true ) {
			if ( $i >= $this->n || '"' !== $this->s[ $i ] ) {
				throw $this->error( 'Expecting property name enclosed in double quotes', $i );
			}
			list( $key, $i ) = $this->string( $i + 1 );
			$i               = $this->ws( $i );
			if ( $i >= $this->n || ':' !== $this->s[ $i ] ) {
				throw $this->error( "Expecting ':' delimiter", $i );
			}
			list( $value, $i ) = $this->scan( $this->ws( $i + 1 ), $depth );
			$out[ $key ]       = $value;
			$i                 = $this->ws( $i );
			if ( $i < $this->n && '}' === $this->s[ $i ] ) {
				return array( $out, $i + 1 );
			}
			if ( $i >= $this->n || ',' !== $this->s[ $i ] ) {
				throw $this->error( "Expecting ',' delimiter", $i );
			}
			$comma = $i;
			$i     = $this->ws( $i + 1 );
			if ( $i < $this->n && '}' === $this->s[ $i ] ) {
				throw $this->error( 'Illegal trailing comma before end of object', $comma );
			}
		}
	}

	/**
	 * Array body after '['.
	 *
	 * @param int $i     Index after '['.
	 * @param int $depth Depth.
	 * @return array{0: PyList, 1: int}
	 * @throws PyJsonError When invalid.
	 */
	private function array( $i, $depth ) {
		$items = array();
		$i     = $this->ws( $i );
		if ( $i < $this->n && ']' === $this->s[ $i ] ) {
			return array( new PyList(), $i + 1 );
		}
		while ( true ) {
			list( $value, $i ) = $this->scan( $i, $depth );
			$items[]           = $value;
			$i                 = $this->ws( $i );
			if ( $i < $this->n && ']' === $this->s[ $i ] ) {
				return array( new PyList( $items ), $i + 1 );
			}
			if ( $i >= $this->n || ',' !== $this->s[ $i ] ) {
				throw $this->error( "Expecting ',' delimiter", $i );
			}
			$comma = $i;
			$i     = $this->ws( $i + 1 );
			if ( $i < $this->n && ']' === $this->s[ $i ] ) {
				throw $this->error( 'Illegal trailing comma before end of array', $comma );
			}
		}
	}
}
