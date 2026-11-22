<?php
/**
 * divi_create_page.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\LayoutInput;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Create a page (draft by default), optionally with a Divi layout.
 */
final class CreatePage extends Tool {

	public function name(): string {
		return 'divi_create_page';
	}

	public function description(): string {
		return 'Create a new page, a draft unless status says otherwise, and optionally apply a Divi layout to it in the same call (tree, markup or Portability export; same steps as divi_apply_layout). The safe way to build a new design: review the draft, then publish it. An invalid layout creates nothing.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array_merge(
				array(
					'title'  => array(
						'type'        => 'string',
						'minLength'   => 1,
						'description' => 'Page title.',
					),
					'slug'   => array(
						'type'        => 'string',
						'description' => 'URL slug.',
					),
					'parent' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Parent page id (0 for none).',
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'draft', 'pending', 'private', 'publish' ),
						'description' => 'Default draft. publish and private need the publish_pages capability.',
					),
				),
				DiviSupport::apply_properties()
			),
			array( 'title' ),
			true
		);
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$type = PostSupport::post_type( 'page' );
		if ( ! current_user_can( $type->cap->create_posts ) ) {
			throw new ToolError( 'You are not allowed to create pages.', 'forbidden' );
		}

		$has_layout = isset( $args['layout'] ) || isset( $args['markup'] ) || isset( $args['portability'] );
		// Resolve and validate first: a bad layout creates nothing.
		$resolved = $has_layout ? LayoutInput::resolve( $args ) : null;

		$plan    = PostSupport::plan(
			$type,
			array_intersect_key( $args, array_flip( array( 'title', 'slug', 'parent', 'status' ) ) ),
			null
		);
		$postarr = array_merge(
			array( 'post_status' => 'draft' ),
			$plan['post'],
			array(
				'post_type'    => 'page',
				'post_author'  => $ctx->user_id,
				'post_content' => '',
			)
		);
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			throw new ToolError( 'Could not create the page: ' . $id->get_error_message(), 'create_failed' );
		}
		$id = (int) $id;

		$out = array_merge(
			array(
				'ok'      => true,
				'post_id' => $id,
				'status'  => get_post_status( $id ),
			),
			DiviSupport::links( $id )
		);
		if ( null === $resolved ) {
			return $out;
		}

		try {
			// The page is new: there is nothing visitors could lose, so no confirmation.
			$applied = DiviSupport::apply(
				get_post( $id ),
				$resolved,
				array_merge( $args, array( 'confirm_published' => true ) ),
				$ctx
			);
		} catch ( ToolError $e ) {
			PostSupport::discard_created( $id );
			throw $e;
		}
		return array_merge( $out, $applied, array( 'status' => get_post_status( $id ) ) );
	}
}
