<?php
/**
 * Tool: get_menu.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a menu with its items.
 */
final class GetMenu extends Tool {

	public function name(): string {
		return 'get_menu';
	}

	public function description(): string {
		return 'Get a navigation menu with its items in display order: id, title, url, type (post_type, taxonomy or custom), object, object_id, parent item id and position.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'menu_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Menu ID (see list_menus).',
				),
			),
			'required'             => array( 'menu_id' ),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$menu = SiteSupport::menu( $args['menu_id'] );

		return array(
			'id'    => (int) $menu->term_id,
			'name'  => $menu->name,
			'slug'  => $menu->slug,
			'items' => SiteSupport::menu_items( (int) $menu->term_id ),
		);
	}
}
