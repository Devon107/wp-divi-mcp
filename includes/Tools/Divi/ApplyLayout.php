<?php
/**
 * divi_apply_layout.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\LayoutInput;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Replace a post's Divi design with a layout, after a backup.
 */
final class ApplyLayout extends Tool {

	public function name(): string {
		return 'divi_apply_layout';
	}

	public function description(): string {
		return 'Replace the Divi design of a page or post with a layout (tree, markup or Portability export). Order: validate (errors: nothing changes) -> published/scheduled/private pages need confirm_published=true -> dry_run returns a preview -> back up the current content (backup_id) -> import images -> merge globals (existing values kept unless overwrite_globals) -> page CSS -> write through Divi. Prefer a new draft page (divi_create_page) for new designs. Undo with divi_restore_backup.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array_merge(
				array(
					'post_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Page or post to change.',
					),
				),
				DiviSupport::apply_properties(),
				array(
					'dry_run'           => array(
						'type'        => 'boolean',
						'description' => 'Validate and preview (globals, images, size); change nothing. Default false.',
					),
					'confirm_published' => DiviSupport::confirm_property(),
				)
			),
			array( 'post_id' ),
			true
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$post     = DiviSupport::editable_post( $args['post_id'] );
		$resolved = LayoutInput::resolve( $args );
		return DiviSupport::apply( $post, $resolved, $args, $ctx );
	}
}
