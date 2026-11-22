<?php
/**
 * MCP prompt definition.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * A prompt template. The render callable receives the arguments array and
 * returns a list of MCP messages ({role, content}).
 */
class Prompt {

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * Argument definitions: [{name, description?, required?}].
	 *
	 * @var array
	 */
	public $arguments;

	/**
	 * @var callable
	 */
	public $render;

	/**
	 * @param string   $name        Prompt name.
	 * @param string   $description Description.
	 * @param array    $arguments   Argument definitions.
	 * @param callable $render      function( array $args ): array of messages.
	 */
	public function __construct( string $name, string $description, array $arguments, callable $render ) {
		$this->name        = $name;
		$this->description = $description;
		$this->arguments   = $arguments;
		$this->render      = $render;
	}
}
