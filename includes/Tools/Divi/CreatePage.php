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
						'enum'        => array( 'draft', 'pending', 'private', 'publish', 'future' ),
						'description' => 'Default draft. publish, private and future need the publish_pages capability; future also needs date.',
					),
					'date'   => array(
						'type'        => 'string',
						'description' => 'Publication date for status=future, in the site timezone (e.g. 2030-01-31 09:00:00) or ISO 8601 with an offset.',
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
			array_intersect_key( $args, array_flip( array( 'title', 'slug', 'parent', 'status', 'date' ) ) ),
			null
		);
		$final = $plan['post']['post_status'] ?? 'draft';
		$post  = $plan['post'];
		if ( null !== $resolved && 'draft' !== $final ) {
			// Never visible while empty: insert a draft, apply, then move to the final status.
			$post['post_status'] = 'draft';
		}
		$postarr = array_merge(
			array( 'post_status' => 'draft' ),
			$post,
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
			// The page is a new draft: nothing visitors could lose, so no confirmation.
			$applied = DiviSupport::apply(
				get_post( $id ),
				$resolved,
				array_merge( $args, array( 'confirm_published' => true ) ),
				$ctx
			);
			if ( 'draft' !== $final ) {
				self::finalize_status( $id, $final, (string) ( $args['slug'] ?? $args['title'] ) );
			}
		} catch ( \Throwable $e ) {
			throw self::discard( $id, $e );
		}
		return array_merge( $out, $applied, DiviSupport::links( $id ), array( 'status' => get_post_status( $id ) ) );
	}

	/**
	 * Move the filled draft to its final status without re-saving the content
	 * (wp_update_post would run kses over the Divi markup again).
	 *
	 * wp_insert_post() leaves post_name and post_date_gmt empty for drafts, and
	 * neither wp_publish_post() nor a raw status update fills them in, so both
	 * are written first: a unique slug, and the GMT date (from post_date).
	 *
	 * @param int    $id     Page id.
	 * @param string $status publish, pending, private or future.
	 * @param string $slug   Requested slug, or the title.
	 * @return void
	 * @throws ToolError update_failed.
	 */
	private static function finalize_status( int $id, string $status, string $slug ): void {
		global $wpdb;
		$post   = get_post( $id );
		$fields = array();
		$base   = '' !== $post->post_name ? $post->post_name : sanitize_title( $slug );
		$unique = wp_unique_post_slug( $base, $id, $status, $post->post_type, (int) $post->post_parent );
		if ( $unique !== $post->post_name ) {
			$fields['post_name'] = $unique;
		}
		if ( 'pending' !== $status && '0000-00-00 00:00:00' === $post->post_date_gmt ) {
			$fields['post_date_gmt'] = get_gmt_from_date( $post->post_date );
		}
		if ( $fields ) {
			if ( false === $wpdb->update( $wpdb->posts, $fields, array( 'ID' => $id ) ) ) {
				throw new ToolError( 'Could not set the page slug and date.', 'update_failed' );
			}
			clean_post_cache( $id );
		}

		if ( 'publish' === $status ) {
			wp_publish_post( $id );
		} else {
			$old = get_post( $id );
			if ( false === $wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => $id ) ) ) {
				throw new ToolError( 'Could not set the page status to ' . $status . '.', 'update_failed' );
			}
			clean_post_cache( $id );
			// Fires future_page too, whose core hook schedules the publication.
			wp_transition_post_status( $status, $old->post_status, get_post( $id ) );
		}
		clean_post_cache( $id );
		if ( get_post_status( $id ) !== $status ) {
			throw new ToolError( 'Could not set the page status to ' . $status . '.', 'update_failed' );
		}
	}

	/**
	 * After a failed apply: trash the page when trash is available, otherwise
	 * make sure it is a draft and say so. It is never left published.
	 *
	 * @param int        $id Page id.
	 * @param \Throwable $e  Failure.
	 * @return ToolError
	 */
	private static function discard( int $id, \Throwable $e ): ToolError {
		$data = $e instanceof ToolError ? $e->data() : array();
		$code = $e instanceof ToolError ? $e->error_code() : 'internal_error';
		$trashed = EMPTY_TRASH_DAYS ? wp_trash_post( $id ) : null;
		if ( $trashed instanceof \WP_Post ) {
			$data['page_trashed'] = $id;
		} else {
			$old = get_post( $id );
			if ( $old instanceof \WP_Post && 'draft' !== $old->post_status ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) );
				clean_post_cache( $id );
				wp_transition_post_status( 'draft', $old->post_status, get_post( $id ) );
			}
			$data['page_left_as_draft'] = $id;
		}
		return new ToolError( $e->getMessage(), $code, $data );
	}
}
