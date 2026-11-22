<?php
/**
 * Tool: site_info.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the site, theme, Divi and the calling user.
 */
final class SiteInfo extends Tool {

	public function name(): string {
		return 'site_info';
	}

	public function description(): string {
		return 'Describe this WordPress site: versions, URLs, active theme, whether Divi is active, the calling user\'s role and key capabilities, and the active plugins (only when the user may manage plugins).';
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
		return 'read';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		PostSupport::validate( $this->input_schema(), $args );

		$theme  = wp_get_theme();
		$parent = $theme->parent();
		$divi   = 'divi' === strtolower( (string) $theme->get_template() );

		$user = get_userdata( $ctx->user_id );
		$caps = array();
		foreach ( array( 'edit_pages', 'publish_pages', 'edit_theme_options', 'upload_files', 'manage_options' ) as $cap ) {
			$caps[ $cap ] = user_can( $ctx->user_id, $cap );
		}

		$out = array(
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'site_url'    => site_url(),
			'home_url'    => home_url(),
			'theme'       => array(
				'name'       => (string) $theme->get( 'Name' ),
				'stylesheet' => $theme->get_stylesheet(),
				'version'    => (string) $theme->get( 'Version' ),
				'parent'     => $parent ? (string) $parent->get( 'Name' ) : null,
			),
			'divi'        => array(
				'active'  => $divi,
				'version' => $divi ? (string) wp_get_theme( $theme->get_template() )->get( 'Version' ) : null,
			),
			'user'        => array(
				'login' => $user ? $user->user_login : '',
				'roles' => $user ? array_values( $user->roles ) : array(),
				'caps'  => $caps,
			),
			'connection'  => array(
				'label' => (string) ( $ctx->connection['label'] ?? '' ),
				'mode'  => (string) ( $ctx->connection['mode'] ?? '' ),
			),
		);

		if ( user_can( $ctx->user_id, 'activate_plugins' ) ) {
			$out['plugins_active'] = $this->active_plugins();
		}
		return $out;
	}

	/**
	 * @return array[] {name, version, plugin}
	 */
	private function active_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$active    = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$out = array();
		foreach ( array_unique( $active ) as $file ) {
			if ( isset( $installed[ $file ] ) ) {
				$out[] = array(
					'name'    => $installed[ $file ]['Name'],
					'version' => $installed[ $file ]['Version'],
					'plugin'  => $file,
				);
			}
		}
		return $out;
	}
}
