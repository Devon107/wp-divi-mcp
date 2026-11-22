<?php
/**
 * Tool: list_menus.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Lists navigation menus and the theme's menu locations.
 */
final class ListMenus extends Tool {

	public function name(): string {
		return 'list_menus';
	}

	public function description(): string {
		return 'List the navigation menus and the menu locations registered by the active theme, with which menu is assigned to each location.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'required'             => array(),
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
		PostSupport::validate( $this->input_schema(), $args );

		$assigned  = get_nav_menu_locations();
		$by_menu   = array();
		$locations = array();
		foreach ( get_registered_nav_menus() as $location => $description ) {
			$menu_id                 = isset( $assigned[ $location ] ) ? (int) $assigned[ $location ] : 0;
			$locations[]             = array(
				'location'    => (string) $location,
				'description' => (string) $description,
				'menu_id'     => $menu_id,
			);
			$by_menu[ $menu_id ][] = (string) $location;
		}

		$menus = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[] = array(
				'id'        => (int) $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => (int) $menu->count,
				'locations' => $by_menu[ (int) $menu->term_id ] ?? array(),
			);
		}
		return array(
			'menus'     => $menus,
			'locations' => $locations,
		);
	}
}
