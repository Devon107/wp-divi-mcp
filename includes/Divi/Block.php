<?php
/**
 * A Divi 5 block (port of divi5.py's Block).
 *
 * Pure PHP, no WordPress dependency, so it also runs in CLI tooling.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * One `<!-- wp:divi/... -->` block: name, attributes and optional children.
 *
 * children === null renders self-closing; an empty array renders an empty container.
 */
class Block {

	/**
	 * @var string Block name, always "divi/...".
	 */
	public $name;

	/**
	 * @var array Block attributes (JSON object; use \stdClass for nested empty objects).
	 */
	public $attrs;

	/**
	 * @var Block[]|null Child blocks, or null for a self-closing block.
	 */
	public $children;

	/**
	 * Column width as a reduced fraction [numerator, denominator] (set by D5::column()).
	 *
	 * @internal Used by D5::row().
	 * @var int[]|null
	 */
	public $width_fraction;

	/**
	 * Tablet width override (set by D5::column()).
	 *
	 * @internal Used by D5::row().
	 * @var mixed
	 */
	public $tablet_width;

	/**
	 * Phone width override (set by D5::column()).
	 *
	 * @internal Used by D5::row().
	 * @var mixed
	 */
	public $phone_width;

	/**
	 * @param string                 $name     Block name; must start with "divi/".
	 * @param array|\stdClass|null   $attrs    Attributes.
	 * @param array<Block|null>|null $children Children (null entries are dropped) or null.
	 * @throws \InvalidArgumentException On a bad name or a child that is not a Block.
	 */
	public function __construct( $name, $attrs = null, $children = null ) {
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'divi/' ) ) {
			throw new \InvalidArgumentException( "Block name must start with 'divi/': " . ( is_string( $name ) ? $name : gettype( $name ) ) );
		}
		$this->name = $name;
		if ( $attrs instanceof \stdClass ) {
			$attrs = get_object_vars( $attrs );
		}
		if ( null !== $attrs && ! is_array( $attrs ) ) {
			throw new \InvalidArgumentException( 'Block attrs must be an array' );
		}
		$this->attrs = $attrs ? $attrs : array();
		if ( null === $children ) {
			$this->children = null;
			return;
		}
		if ( ! is_array( $children ) ) {
			throw new \InvalidArgumentException( 'Block children must be an array or null' );
		}
		$this->children = array();
		foreach ( $children as $child ) {
			if ( null === $child ) {
				continue;
			}
			if ( ! $child instanceof self ) {
				throw new \InvalidArgumentException( "Children of {$name} must be Block objects" );
			}
			$this->children[] = $child;
		}
	}
}
