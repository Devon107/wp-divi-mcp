<?php
/**
 * Base class for MCP tools.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * A callable MCP tool. Subclasses declare their metadata and implement execute().
 */
abstract class Tool {

	/**
	 * Unique tool name.
	 *
	 * @return string
	 */
	abstract public function name(): string;

	/**
	 * Human/LLM-readable description.
	 *
	 * @return string
	 */
	abstract public function description(): string;

	/**
	 * JSON Schema (as PHP array) describing the arguments object.
	 *
	 * @return array
	 */
	abstract public function input_schema(): array;

	/**
	 * Run the tool.
	 *
	 * @param array   $args Validated-by-the-tool arguments.
	 * @param Context $ctx  Request context.
	 * @return array Structured result.
	 * @throws ToolError For expected failures.
	 */
	abstract public function execute( array $args, Context $ctx ): array;

	/**
	 * Whether the tool never modifies anything.
	 *
	 * @return bool
	 */
	public function read_only(): bool {
		return false;
	}

	/**
	 * Whether the tool may delete or overwrite data.
	 *
	 * @return bool
	 */
	public function destructive(): bool {
		return false;
	}

	/**
	 * WordPress capability the calling user needs.
	 *
	 * @return string
	 */
	public function capability(): string {
		return 'edit_posts';
	}
}
