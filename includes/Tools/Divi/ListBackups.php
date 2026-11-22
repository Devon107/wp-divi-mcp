<?php
/**
 * divi_list_backups.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Backups;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Backups the current user may restore, newest first.
 */
final class ListBackups extends Tool {

	const MAX_LIMIT = 200;

	public function name(): string {
		return 'divi_list_backups';
	}

	public function description(): string {
		return 'List the backups taken before Divi changes (page content, globals, Theme Builder), newest first, without their content: id, kind, source_id (post id; 0 for globals), connection, created, bytes. Only backups you may restore are listed. Restore one with divi_restore_backup.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'post_id' => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Only backups of this post (0 = site-wide: globals).',
				),
				'kind'    => array(
					'type'        => 'string',
					'enum'        => Backups::KINDS,
					'description' => 'Only this kind.',
				),
				'limit'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_LIMIT,
					'description' => 'Most backups to return. Default 20.',
				),
			)
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args  = PostSupport::validate( $this->input_schema(), $args );
		$limit = min( self::MAX_LIMIT, $args['limit'] ?? 20 );
		$post  = $args['post_id'] ?? null;
		if ( null !== $post && $post > 0 && ! current_user_can( 'edit_post', $post ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post . ', so you cannot see its backups.', 'forbidden' );
		}

		$out = array();
		// Read the most the store keeps per query, then drop what the user may not see.
		foreach ( Backups::list( $post, $args['kind'] ?? null, self::MAX_LIMIT ) as $backup ) {
			if ( self::can_access( $backup ) ) {
				$out[] = $backup;
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return array( 'backups' => $out );
	}

	/**
	 * Whether the current user may see and restore a backup: page content
	 * needs edit_post on its source; globals and Theme Builder need
	 * edit_theme_options.
	 *
	 * @param array $backup Backups::list()/get() entry.
	 * @return bool
	 */
	public static function can_access( array $backup ): bool {
		if ( 'post_content' === $backup['kind'] ) {
			return $backup['source_id'] > 0 && current_user_can( 'edit_post', $backup['source_id'] );
		}
		return current_user_can( 'edit_theme_options' );
	}
}
