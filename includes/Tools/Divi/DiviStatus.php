<?php
/**
 * divi_status.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Catalog;
use WPDM\Divi\Env;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Divi version and what the current user may do with the Divi tools.
 */
final class DiviStatus extends Tool {

	public function name(): string {
		return 'divi_status';
	}

	public function description(): string {
		return 'Divi status: whether Divi 5 is active, its version (stamped on new blocks), the module catalog version, and which Divi actions the current user may take (globals, publishing, uploads, raw HTML).';
	}

	public function input_schema(): array {
		return DiviSupport::schema( array() );
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		PostSupport::validate( $this->input_schema(), $args );
		$theme   = wp_get_theme();
		$catalog = Catalog::load();
		return array(
			'divi_active'     => Env::divi_active(),
			'divi_version'    => Env::divi_version(),
			'theme'           => $theme->get( 'Name' ),
			'parent_theme'    => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			'catalog_version' => $catalog['diviVersion'] ?? null,
			'plugin_version'  => defined( 'WPDM_VERSION' ) ? WPDM_VERSION : null,
			'can'             => array(
				'edit_pages'         => current_user_can( 'edit_pages' ),
				'publish_pages'      => current_user_can( 'publish_pages' ),
				'edit_theme_options' => current_user_can( 'edit_theme_options' ),
				'upload_files'       => current_user_can( 'upload_files' ),
				'unfiltered_html'    => current_user_can( 'unfiltered_html' ),
			),
		);
	}
}
