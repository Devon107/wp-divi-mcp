<?php
/**
 * A JSON list as decoded by PyJson / Validator.
 *
 * The validator models Python values: JSON objects are PHP arrays (string keys,
 * insertion order) and JSON lists are PyList, so `{}` and `[]` stay distinct the
 * way Python's dict and list are.
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Wrapper for a list value.
 */
final class PyList {

	/**
	 * Items, 0-indexed.
	 *
	 * @var array<int, mixed>
	 */
	public $items;

	/**
	 * @param array<int, mixed> $items Items.
	 */
	public function __construct( array $items = array() ) {
		$this->items = array_values( $items );
	}
}
