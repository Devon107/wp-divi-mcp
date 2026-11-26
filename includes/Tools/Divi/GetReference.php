<?php
/**
 * Tool: divi_get_reference.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Knowledge\Resources;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one of the divi:// reference resources, for clients that do not let the model read
 * MCP resources, and so the layout schema need not be repeated in every tool definition.
 */
final class GetReference extends Tool {

	public function name(): string {
		return 'divi_get_reference';
	}

	public function description(): string {
		return 'Read a Divi building reference: divi://guide (workflow, layout tree format, examples, safety rules; read it before building pages), divi://schema/layout (full JSON Schema of the layout argument of divi_build_layout, divi_validate_layout, divi_apply_layout, divi_create_page and divi_set_theme_builder_layout; read it once before writing a layout tree), divi://attributes (block attribute shapes), divi://modules/index (module list) or divi://modules/{name} (one module). Not needed for editing text, dates, SEO or translations. Changes nothing.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'uri' => array(
					'type'        => 'string',
					'description' => 'divi://guide, divi://schema/layout, divi://attributes, divi://modules/index or divi://modules/{name}.',
				),
			),
			'required'             => array( 'uri' ),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$registry = new Registry();
		Resources::register( $registry );
		$resource = $registry->read_resource( trim( $args['uri'] ) );
		if ( null === $resource ) {
			throw new ToolError( 'Unknown reference "' . $args['uri'] . '". Use divi://guide, divi://schema/layout, divi://attributes, divi://modules/index or divi://modules/{name}.', 'not_found' );
		}
		return $resource;
	}
}
