<?php
/**
 * divi_delete_theme_builder_template.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Delete a template of the published Theme Builder, after backing up its layouts.
 */
final class DeleteThemeBuilderTemplate extends Tool {

	public function name(): string {
		return 'divi_delete_theme_builder_template';
	}

	public function description(): string {
		return 'Delete a template of the published Divi Theme Builder (Divi moves it to the trash and detaches it). The Default Website Template is refused; a template that is enabled and assigned (use_on) needs confirm_live_template=true. The content of each of its header/body/footer layouts is backed up first (backups); the template itself cannot be restored by divi_restore_backup, but the layout content stays recoverable.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'template_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Template id (divi_list_theme_builder_templates).',
				),
				'confirm_live_template' => ThemeBuilderSupport::confirm_live_property(),
			),
			array( 'template_id' )
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$template = ThemeBuilderSupport::require_template( $args['template_id'] );
		if ( ThemeBuilderSupport::is_default( $template ) ) {
			throw new ToolError( 'Refusing to delete the Default Website Template (template ' . $args['template_id'] . '): it holds the header and footer of the whole site.', 'refused', array( 'template_id' => $args['template_id'] ) );
		}

		ThemeBuilderSupport::check_live( $template, (bool) ( $args['confirm_live_template'] ?? false ) );

		$backups = array();
		foreach ( ThemeBuilderSupport::SLOTS as $slot ) {
			$layout_id = ThemeBuilderSupport::layout_id( $template, $slot );
			if ( null === ThemeBuilderSupport::layout_content( $layout_id ) ) {
				continue;
			}
			$saved     = ThemeBuilderSupport::backup_slot( $template, $slot, $ctx, array( 'deleted' => true ) );
			$backups[] = array(
				'layout_type' => $slot,
				'layout_id'   => $layout_id,
				'backup_id'   => $saved['backup_id'],
			);
		}

		try {
			ThemeBuilderSupport::call( 'delete-template', array( 'template_id' => $args['template_id'] ) );
		} catch ( ToolError $e ) {
			throw new ToolError(
				$e->getMessage() . ( $backups ? ' The layout content was backed up first (backups ' . implode( ', ', array_column( $backups, 'backup_id' ) ) . ').' : '' ),
				$e->error_code(),
				array_merge( $e->data(), array( 'backups' => $backups ) )
			);
		}

		return array(
			'ok'          => true,
			'template_id' => $args['template_id'],
			'backups'     => $backups,
		);
	}
}
