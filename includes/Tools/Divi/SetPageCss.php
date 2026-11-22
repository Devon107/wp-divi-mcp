<?php
/**
 * divi_set_page_css.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Replace a page's Divi custom CSS, after a backup.
 */
final class SetPageCss extends Tool {

	public function name(): string {
		return 'divi_set_page_css';
	}

	public function description(): string {
		return 'Replace the Divi page custom CSS (Page Settings > Advanced > Custom CSS) of a page or post. The current content and CSS are backed up first (backup_id; undo with divi_restore_backup). Published, scheduled or private pages need confirm_published=true. An empty css clears it.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'post_id'           => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page or post.',
				),
				'css'               => array(
					'type'        => 'string',
					'description' => 'The whole page CSS (replaces the current one).',
				),
				'confirm_published' => DiviSupport::confirm_property(),
			),
			array( 'post_id', 'css' )
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = DiviSupport::editable_post( $args['post_id'] );
		DiviSupport::check_published( $post, (bool) ( $args['confirm_published'] ?? false ) );

		$backup_id = DiviSupport::backup_post( $post->ID, $ctx, 'before_page_css' );
		$stored    = DiviSupport::set_page_css( $post->ID, $args['css'] );
		return array(
			'ok'        => true,
			'post_id'   => $post->ID,
			'bytes'     => strlen( $stored ),
			'backup_id' => $backup_id,
		);
	}
}
