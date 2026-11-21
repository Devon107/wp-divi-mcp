<?php
/**
 * Tool: update_term.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Edits a term.
 */
final class UpdateTerm extends Tool {

	public function name(): string {
		return 'update_term';
	}

	public function description(): string {
		return 'Edit a term\'s name, slug, description or parent. Only the fields you pass change. A term cannot become its own parent or a child of its own descendant.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Term ID.',
				),
				'taxonomy'    => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Taxonomy the term belongs to.',
				),
				'name'        => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'New name.',
				),
				'slug'        => array(
					'type'        => 'string',
					'description' => 'New slug.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'New description.',
				),
				'parent'      => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'New parent term ID, 0 for none (hierarchical taxonomies only).',
				),
			),
			'required'             => array( 'id', 'taxonomy' ),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$taxonomy = SiteSupport::taxonomy( $args['taxonomy'] );
		$term     = SiteSupport::find_term( $args['id'], $taxonomy->name );

		if ( ! current_user_can( $taxonomy->cap->edit_terms ) || ! current_user_can( 'edit_term', $term->term_id ) ) {
			throw new ToolError( 'You are not allowed to edit term ' . $term->term_id . '.', 'forbidden' );
		}
		if ( ! array_intersect( array( 'name', 'slug', 'description', 'parent' ), array_keys( $args ) ) ) {
			throw new ToolError( 'Provide at least one of: name, slug, description, parent.', 'invalid_argument' );
		}
		if ( isset( $args['name'] ) && '' === trim( $args['name'] ) ) {
			throw new ToolError( 'Argument "name" must not be empty.', 'invalid_argument' );
		}

		$update = array();
		foreach ( array( 'name', 'slug', 'description' ) as $field ) {
			if ( isset( $args[ $field ] ) ) {
				$update[ $field ] = $args[ $field ];
			}
		}
		if ( isset( $args['parent'] ) ) {
			if ( $args['parent'] > 0 ) {
				TermRules::assert_parent( $taxonomy, $args['parent'], (int) $term->term_id );
			}
			$update['parent'] = $args['parent'];
		}

		$result = wp_update_term( $term->term_id, $taxonomy->name, wp_slash( $update ) );
		if ( is_wp_error( $result ) ) {
			throw new ToolError( 'Could not update the term: ' . $result->get_error_message(), 'term_error' );
		}

		return SiteSupport::term_data( SiteSupport::find_term( $term->term_id, $taxonomy->name ) );
	}
}
