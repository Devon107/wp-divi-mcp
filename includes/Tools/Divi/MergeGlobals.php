<?php
/**
 * divi_merge_globals.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Globals;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Merge global colors/variables by id; existing values are kept unless overwrite.
 */
final class MergeGlobals extends Tool {

	public function name(): string {
		return 'divi_merge_globals';
	}

	public function description(): string {
		return 'Add global colors/variables to the site, by id. Existing ids with a different value are reported as conflicts and kept, unless overwrite=true; ids that exist with another variable type and the built-in Customizer colors are never changed. The current globals are backed up before any write (backup_id; undo with divi_restore_backup). Use dry_run=true to preview.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'colors'    => array(
					'type'        => 'array',
					'description' => 'Colors as [["gcid-brand", {"color": "#1a73e8", "label": "Brand"}], ...].',
					'items'       => array( 'type' => 'array' ),
				),
				'variables' => array(
					'type'        => 'array',
					'description' => 'Variables as [{"id": "gvid-radius", "type": "numbers|strings|images|links|fonts|gradients", "value": "12px", "label": "Radius"}, ...].',
					'items'       => array( 'type' => 'object' ),
				),
				'overwrite' => array(
					'type'        => 'boolean',
					'description' => 'Replace existing values that differ. Default false.',
				),
				'dry_run'   => array(
					'type'        => 'boolean',
					'description' => 'Only report what would change. Default false.',
				),
			)
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		return Globals::merge(
			$args['colors'] ?? array(),
			$args['variables'] ?? array(),
			(bool) ( $args['overwrite'] ?? false ),
			(bool) ( $args['dry_run'] ?? false ),
			(string) ( $ctx->connection['label'] ?? '' )
		);
	}
}
