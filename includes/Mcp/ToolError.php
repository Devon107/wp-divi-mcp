<?php
/**
 * Expected, user-facing tool failure.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by tools for expected failures (bad input, not found, ...).
 * The server reports it to the client as a tool result with isError=true,
 * never as a JSON-RPC protocol error.
 */
class ToolError extends \RuntimeException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Extra structured data about the failure.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * @param string $message User-facing message.
	 * @param string $code    Machine-readable code.
	 * @param array  $data    Extra structured data.
	 */
	public function __construct( string $message, string $code = 'tool_error', array $data = array() ) {
		parent::__construct( $message );
		$this->error_code = $code;
		$this->data       = $data;
	}

	/**
	 * Machine-readable code (RuntimeException::getCode() is int-only).
	 *
	 * @return string
	 */
	public function error_code(): string {
		return $this->error_code;
	}

	/**
	 * Extra structured data.
	 *
	 * @return array
	 */
	public function data(): array {
		return $this->data;
	}
}
