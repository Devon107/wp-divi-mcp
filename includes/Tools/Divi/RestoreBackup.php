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
		return 'Restore a backup from divi_list_backups. Page content: the current content is backed up first (backup_id), then the stored content and page CSS are written back; published, scheduled or private pages need confirm_published=true. Globals: the site\'s global colors/variables are replaced by the snapshot (also after a backup; variables added since are archived, Divi never deletes them). Theme Builder: the stored header/footer is written back into the same template (after a backup of the current one); the Default Website Template needs confirm_default_template=true. A layout that did not exist before, or a deleted template, cannot be restored this way.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'backup_id'         => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Backup id.',
				),
				'confirm_published'        => DiviSupport::confirm_property(),
				'confirm_default_template' => ThemeBuilderSupport::confirm_property(),
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
			case 'theme_builder':
				return self::restore_theme_builder( $backup, (bool) ( $args['confirm_default_template'] ?? false ), $ctx );
			default:
				throw new ToolError( 'Restoring ' . $backup['kind'] . ' backups is not supported.', 'not_supported' );
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

	/**
	 * Upsert a stored header/footer back into the same template slot (live=true).
	 *
	 * @param array   $backup  Backup with content.
	 * @param bool    $confirm confirm_default_template.
	 * @param Context $ctx     Context.
	 * @return array
	 * @throws ToolError template_missing, not_supported, confirmation_required, or a Divi failure.
	 */
	private static function restore_theme_builder( array $backup, bool $confirm, Context $ctx ): array {
		$meta        = $backup['meta'];
		$template_id = (int) $backup['source_id'];
		$type        = (string) ( $meta['layout_type'] ?? '' );
		$template    = ThemeBuilderSupport::find( $template_id ); // A deleted (trashed) template is no longer listed.
		if ( null === $template ) {
			throw new ToolError(
				'Theme Builder template ' . $template_id . ' no longer exists, so backup ' . $backup['id'] . ' cannot be put back into it. Its layout content is still kept in that backup (data.content_backup_id); rebuild it on another template with divi_set_theme_builder_layout.',
				'template_missing',
				array(
					'template_id'       => $template_id,
					'content_backup_id' => $backup['id'],
				)
			);
		}
		if ( '' === $backup['content'] || ! empty( $meta['created'] ) ) {
			throw new ToolError(
				'Backup ' . $backup['id'] . ' records that template ' . $template_id . ' had no ' . ( '' === $type ? 'layout' : $type ) . ' before; there is nothing to restore. To remove the layout, do it in Divi > Theme Builder.',
				'not_supported',
				array( 'template_id' => $template_id )
			);
		}
		if ( ! in_array( $type, ThemeBuilderSupport::LAYOUT_TYPES, true ) ) {
			throw new ToolError( 'Divi can only write header and footer layouts; the ' . ( '' === $type ? 'unknown' : $type ) . ' layout in backup ' . $backup['id'] . ' must be restored in Divi > Theme Builder.', 'not_supported', array( 'template_id' => $template_id ) );
		}
		ThemeBuilderSupport::check_default( $template, $confirm );

		$safety = ThemeBuilderSupport::backup_slot( $template, $type, $ctx, array( 'reason' => 'before_restore' ) );
		try {
			$result = ThemeBuilderSupport::upsert( $template_id, $type, $backup['content'] );
		} catch ( ToolError $e ) {
			throw new ToolError(
				$e->getMessage() . ' The ' . $type . ' before the restore is in backup ' . $safety['backup_id'] . '.',
				$e->error_code(),
				array_merge( $e->data(), array( 'backup_id' => $safety['backup_id'] ) )
			);
		}

		return array(
			'ok'            => true,
			'kind'          => 'theme_builder',
			'template_id'   => $template_id,
			'layout_type'   => $type,
			'layout_id'     => $result['layout_id'],
			'restored_from' => $backup['id'],
			'backup_id'     => $safety['backup_id'],
		);
	}
}
