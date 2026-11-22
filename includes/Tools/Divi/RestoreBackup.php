<?php
/**
 * divi_restore_backup.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Backups;
use WPDM\Divi\Globals;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Put back what a backup holds, after backing up what it replaces.
 */
final class RestoreBackup extends Tool {

	public function name(): string {
		return 'divi_restore_backup';
	}

	public function description(): string {
		return 'Restore a backup from divi_list_backups. Page content: the current content is backed up first (backup_id), then the stored content and page CSS are written back; published, scheduled or private pages need confirm_published=true. Globals: the site\'s global colors/variables are replaced by the snapshot (also after a backup; variables added since are archived, Divi never deletes them).';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'backup_id'         => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Backup id.',
				),
				'confirm_published' => DiviSupport::confirm_property(),
			),
			array( 'backup_id' )
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args   = PostSupport::validate( $this->input_schema(), $args );
		$backup = Backups::get( $args['backup_id'] );
		if ( null === $backup ) {
			throw new ToolError( 'Backup ' . $args['backup_id'] . ' not found.', 'not_found' );
		}
		if ( ! ListBackups::can_access( $backup ) ) {
			throw new ToolError( 'You are not allowed to restore backup ' . $backup['id'] . '.', 'forbidden' );
		}
		$label = (string) ( $ctx->connection['label'] ?? '' );

		switch ( $backup['kind'] ) {
			case 'post_content':
				return self::restore_post( $backup, (bool) ( $args['confirm_published'] ?? false ), $ctx );
			case 'globals':
				$snapshot = json_decode( $backup['content'], true );
				if ( ! is_array( $snapshot ) ) {
					throw new ToolError( 'Backup ' . $backup['id'] . ' does not hold a globals snapshot.', 'invalid_backup' );
				}
				return array_merge(
					array(
						'ok'            => true,
						'kind'          => 'globals',
						'restored_from' => $backup['id'],
					),
					Globals::restore( $snapshot, $label )
				);
			default:
				throw new ToolError( 'Restoring ' . $backup['kind'] . ' backups is not supported yet.', 'not_supported_yet' );
		}
	}

	/**
	 * @param array   $backup  Backup with content.
	 * @param bool    $confirm confirm_published.
	 * @param Context $ctx     Context.
	 * @return array
	 * @throws ToolError On refusal or failure.
	 */
	private static function restore_post( array $backup, bool $confirm, Context $ctx ): array {
		$post = DiviSupport::editable_post( $backup['source_id'] );
		DiviSupport::check_published( $post, $confirm );

		$safety  = DiviSupport::backup_post( $post->ID, $ctx, 'before_restore' );
		$content = $backup['content'];
		$meta    = $backup['meta'];
		try {
			if ( false !== strpos( $content, '<!-- wp:divi/' ) ) {
				DiviSupport::set_layout( $post->ID, $content );
				$method = 'set-layout';
			} else {
				// Divi's set-layout refuses empty content; non-Divi content goes back as is too.
				$result = wp_update_post(
					wp_slash(
						array(
							'ID'           => $post->ID,
							'post_content' => $content,
						)
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					throw new ToolError( 'Could not restore the content: ' . $result->get_error_message(), 'update_failed' );
				}
				$method = 'wp_update_post';
			}
			if ( array_key_exists( 'custom_css', $meta ) ) {
				update_post_meta( $post->ID, '_et_pb_custom_css', wp_slash( (string) $meta['custom_css'] ) );
			}
			if ( array_key_exists( 'use_builder', $meta ) && '' === (string) $meta['use_builder'] && 'wp_update_post' === $method ) {
				delete_post_meta( $post->ID, '_et_pb_use_builder' );
			}
		} catch ( ToolError $e ) {
			throw new ToolError(
				$e->getMessage() . ' The content before the restore is in backup ' . $safety . '.',
				$e->error_code(),
				array_merge( $e->data(), array( 'backup_id' => $safety ) )
			);
		}

		return array(
			'ok'            => true,
			'kind'          => 'post_content',
			'post_id'       => $post->ID,
			'restored_from' => $backup['id'],
			'backup_id'     => $safety,
			'method'        => $method,
		);
	}
}
