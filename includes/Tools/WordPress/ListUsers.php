<?php
/**
 * Tool: list_users.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists users, as much as the caller may see.
 */
final class ListUsers extends Tool {

	public function name(): string {
		return 'list_users';
	}

	public function description(): string {
		return 'List users (id, login, display name, roles), e.g. to pick a post author. Callers with the list_users capability see everyone, with email addresses; others only see users who can write content, without emails. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'role'     => array(
					'type'        => 'string',
					'description' => 'Only users with this role (administrator, editor, author, ...).',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Search text (login, display name; email too when the caller may list users).',
				),
				'per_page' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => PostSupport::MAX_PER_PAGE,
					'description' => 'Items per page. Default 20, capped at 100.',
				),
				'page'     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page number, starting at 1.',
				),
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$per_page = PostSupport::per_page( $args );
		$page     = PostSupport::page( $args );
		$full     = current_user_can( 'list_users' );

		$query = array(
			'number'      => $per_page,
			'paged'       => $page,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'count_total' => true,
		);
		if ( isset( $args['role'] ) ) {
			if ( ! wp_roles()->is_role( $args['role'] ) ) {
				throw new ToolError( 'Unknown role "' . $args['role'] . '".', 'invalid_argument' );
			}
			$query['role'] = $args['role'];
		}
		if ( ! $full ) {
			// Only people who can write content, and never matched by email.
			$query['capability'] = 'edit_posts';
		}
		if ( isset( $args['search'] ) && '' !== trim( $args['search'] ) ) {
			$query['search']         = '*' . trim( $args['search'], '* ' ) . '*';
			$query['search_columns'] = $full
				? array( 'user_login', 'user_email', 'user_nicename', 'display_name' )
				: array( 'user_login', 'user_nicename', 'display_name' );
		}

		$users = new \WP_User_Query( $query );
		$items = array();
		foreach ( $users->get_results() as $user ) {
			$row = array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'roles'        => array_values( (array) $user->roles ),
			);
			if ( $full ) {
				$row['email'] = $user->user_email;
			}
			$items[] = $row;
		}
		$total = (int) $users->get_total();
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}
}
