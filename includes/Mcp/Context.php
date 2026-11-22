<?php
/**
 * Per-request execution context.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Who is calling and under which restrictions. Built by the transport layer.
 */
class Context {

	/**
	 * WordPress user the request acts as.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Connection / token metadata supplied by the transport.
	 *
	 * @var array
	 */
	public $connection;

	/**
	 * When true, only read-only tools may run.
	 *
	 * @var bool
	 */
	public $read_only;

	/**
	 * MCP session id, if any.
	 *
	 * @var string|null
	 */
	public $session_id;

	/**
	 * @param int         $user_id    WordPress user id.
	 * @param array       $connection Connection metadata.
	 * @param bool        $read_only  Read-only mode.
	 * @param string|null $session_id Session id.
	 */
	public function __construct( int $user_id, array $connection = array(), bool $read_only = false, ?string $session_id = null ) {
		$this->user_id    = $user_id;
		$this->connection = $connection;
		$this->read_only  = $read_only;
		$this->session_id = $session_id;
	}
}
