<?php
/**
 * Error in a JSON layout tree (see LayoutTree).
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * A layout tree error with the path of the offending node or value.
 *
 * Paths look like `blocks[0].section.children[1].pattern(icon_card).args.icon_value`.
 * The message is prefixed with the path ("<path>: <message>").
 */
class LayoutError extends \InvalidArgumentException {

	/**
	 * @var string Path in the tree ("" for the tree itself).
	 */
	private $path;

	/**
	 * @param string          $path     Path in the tree.
	 * @param string          $message  What was expected and what was found.
	 * @param \Throwable|null $previous Wrapped D5/Patterns exception, if any.
	 */
	public function __construct( string $path, string $message, ?\Throwable $previous = null ) {
		$this->path = $path;
		parent::__construct( ( '' !== $path ? $path . ': ' : '' ) . $message, 0, $previous );
	}

	/**
	 * Path of the offending node or value.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}
}
