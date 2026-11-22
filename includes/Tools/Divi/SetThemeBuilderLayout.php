<?php
/**
 * divi_set_theme_builder_layout.
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
 * Create or replace the header or footer of a published Theme Builder template.
 */
final class SetThemeBuilderLayout extends Tool {

	public function name(): string {
		return 'divi_set_theme_builder_layout';
	}

	public function description(): string {
		return 'Create or replace the header or footer of a template of the published Divi Theme Builder, from a layout (tree, markup or Portability export; it needs a section with Divi modules and no Post Content module). Order: template must exist -> the Default Website Template needs confirm_default_template=true (it changes the whole site); any other template that is enabled and assigned (use_on) needs confirm_live_template=true -> validate (errors: nothing changes) -> back up the current layout (backup_id) -> write through Divi. Safer: divi_create_theme_builder_template, set the layout there, review, then assign it in Divi > Theme Builder. Undo with divi_restore_backup. Body layouts are not supported by Divi\'s API.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'template_id'              => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Template id (divi_list_theme_builder_templates).',
				),
				'layout_type'              => array(
					'type'        => 'string',
					'enum'        => ThemeBuilderSupport::LAYOUT_TYPES,
					'description' => 'Which slot to set: header or footer.',
				),
				'confirm_default_template' => ThemeBuilderSupport::confirm_property(),
				'confirm_live_template'    => ThemeBuilderSupport::confirm_live_property(),
			),
			array( 'template_id', 'layout_type' ),
			true
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
		$type     = $args['layout_type'];
		$template = ThemeBuilderSupport::require_template( $args['template_id'] );
		ThemeBuilderSupport::check_writable( $template, $args );
		$resolved = LayoutInput::resolve( $args );

		$backup = ThemeBuilderSupport::backup_slot( $template, $type, $ctx );
		try {
			$result = ThemeBuilderSupport::upsert( (int) $template['id'], $type, $resolved['content'] );
		} catch ( ToolError $e ) {
			throw new ToolError(
				$e->getMessage() . ' The previous ' . $type . ' is in backup ' . $backup['backup_id'] . '.',
				$e->error_code(),
				array_merge( $e->data(), array( 'backup_id' => $backup['backup_id'] ) )
			);
		}

		return array(
			'ok'                 => true,
			'template_id'        => (int) $template['id'],
			'layout_type'        => $type,
			'layout_id'          => $result['layout_id'],
			'previous_layout_id' => $backup['layout_id'],
			'backup_id'          => $backup['backup_id'],
			'action'             => $result['action'],
			'warnings'           => $resolved['report']->warnings,
		);
	}
}
