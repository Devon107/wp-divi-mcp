<?php
/**
 * divi_create_theme_builder_template.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Create an unassigned template in the published Theme Builder.
 */
final class CreateThemeBuilderTemplate extends Tool {

	public function name(): string {
		return 'divi_create_theme_builder_template';
	}

	public function description(): string {
		return 'Create a new, unassigned template in the published Divi Theme Builder. It applies to no page until it is assigned in Divi > Theme Builder, so it is the safe place to build and review a header or footer (divi_set_theme_builder_layout). If the Theme Builder has no templates yet, Divi also creates an empty Default Website Template.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'title' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Template title shown in Divi > Theme Builder.',
				),
			),
			array( 'title' )
		);
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$data = ThemeBuilderSupport::call( 'create-template', array( 'title' => $args['title'] ) );
		return array(
			'ok'               => true,
			'template_id'      => (int) ( $data['id'] ?? 0 ),
			'theme_builder_id' => (int) ( $data['themeBuilderId'] ?? 0 ),
			'template'         => $data['template'] ?? null,
		);
	}
}
