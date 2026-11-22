<?php
/**
 * Tool: create_term.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Creates a term in a taxonomy.
 */
final class CreateTerm extends Tool {

	public function name(): string {
		return 'create_term';
	}

	public function description(): string {
		return 'Create a term (category, tag or custom taxonomy term). Needs the taxonomy\'s term management capability. A parent is only valid for hierarchical taxonomies and must be a term of the same taxonomy.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'taxonomy'    => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Taxonomy name, e.g. category or post_tag.',
				),
				'name'        => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Term name.',
				),
				'slug'        => array(
					'type'        => 'string',
					'description' => 'URL slug (generated from the name when omitted).',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Term description.',
				),
				'parent'      => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Parent term ID (hierarchical taxonomies only).',
				),
			),
			'required'             => array( 'taxonomy', 'name' ),
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

		if ( ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			throw new ToolError( 'You are not allowed to create terms in "' . $taxonomy->name . '".', 'forbidden' );
		}
		if ( '' === trim( $args['name'] ) ) {
			throw new ToolError( 'Argument "name" must not be empty.', 'invalid_argument' );
		}

		$insert = array();
		if ( isset( $args['slug'] ) ) {
			$insert['slug'] = $args['slug'];
		}
		if ( isset( $args['description'] ) ) {
			$insert['description'] = $args['description'];
		}
		if ( ! empty( $args['parent'] ) ) {
			TermRules::assert_parent( $taxonomy, (int) $args['parent'], 0 );
			$insert['parent'] = (int) $args['parent'];
		}

		$result = wp_insert_term( wp_slash( $args['name'] ), $taxonomy->name, wp_slash( $insert ) );
		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				throw new ToolError( 'A term with this name or slug already exists in "' . $taxonomy->name . '".', 'term_exists', array( 'term_id' => (int) $result->get_error_data() ) );
			}
			throw new ToolError( 'Could not create the term: ' . $result->get_error_message(), 'term_error' );
		}

		return SiteSupport::term_data( SiteSupport::find_term( (int) $result['term_id'], $taxonomy->name ) );
	}
}
