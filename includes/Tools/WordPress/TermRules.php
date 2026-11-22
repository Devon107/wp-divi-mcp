<?php
/**
 * Parent rules shared by create_term and update_term.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Validates a proposed term parent before anything is written.
 */
final class TermRules {

	/**
	 * @param \WP_Taxonomy $taxonomy Taxonomy.
	 * @param int          $parent   Proposed parent term id (greater than 0).
	 * @param int          $term_id  Term being edited, 0 when creating.
	 * @return void
	 * @throws ToolError invalid_argument.
	 */
	public static function assert_parent( \WP_Taxonomy $taxonomy, int $parent, int $term_id ): void {
		if ( ! $taxonomy->hierarchical ) {
			throw new ToolError( '"' . $taxonomy->name . '" is not hierarchical: terms cannot have a parent.', 'invalid_argument' );
		}
		$candidate = get_term( $parent, $taxonomy->name );
		if ( ! $candidate instanceof \WP_Term ) {
			throw new ToolError( 'Parent term ' . $parent . ' does not exist in "' . $taxonomy->name . '".', 'invalid_argument' );
		}
		if ( $term_id > 0 ) {
			$descendants = get_term_children( $term_id, $taxonomy->name );
			if ( $parent === $term_id || ( is_array( $descendants ) && in_array( $parent, array_map( 'intval', $descendants ), true ) ) ) {
				throw new ToolError( 'A term cannot be its own parent or a child of its own descendant.', 'invalid_argument' );
			}
		}
	}
}
