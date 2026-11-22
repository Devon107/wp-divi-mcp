<?php
/**
 * MCP resource definition.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * A static, readable resource. The reader returns the text content as a string.
 */
class Resource {

	/**
	 * @var string
	 */
	public $uri;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var string
	 */
	public $mime;

	/**
	 * Callable returning the content string.
	 *
	 * @var callable
	 */
	public $reader;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * @param string   $uri         Resource URI.
	 * @param string   $name        Display name.
	 * @param string   $mime        MIME type.
	 * @param callable $reader      Returns the content string.
	 * @param string   $description Optional description.
	 */
	public function __construct( string $uri, string $name, string $mime, callable $reader, string $description = '' ) {
		$this->uri         = $uri;
		$this->name        = $name;
		$this->mime        = $mime;
		$this->reader      = $reader;
		$this->description = $description;
	}
}
