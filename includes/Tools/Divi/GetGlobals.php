<?php
/**
 * divi_get_globals.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Globals;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * The site's Divi global colors and variables.
 */
final class GetGlobals extends Tool {

	public function name(): string {
		return 'divi_get_globals';
	}

	public function description(): string {
		return 'Read the site\'s Divi global colors and global variables (id, label, value, type, status). Reference them in a layout tree with {"$color_var": "gcid-..."} / {"$content_var": "gvid-..."}. Built-in colors (primary, secondary, heading, body, link) come from the Customizer and are never changed by these tools.';
	}

	public function input_schema(): array {
		return DiviSupport::schema( array() );
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
		$globals = Globals::read();
		$colors  = array();
		foreach ( (array) $globals['colors'] as $id => $c ) {
			$c        = (array) $c;
			$colors[] = array(
				'id'      => (string) $id,
				'label'   => $c['label'] ?? null,
				'color'   => $c['color'] ?? null,
				'status'  => $c['status'] ?? 'active',
				'builtin' => in_array( (string) $id, Globals::BUILTIN_COLORS, true ),
			);
		}
		$variables = array();
		foreach ( (array) $globals['variables'] as $type => $items ) {
			foreach ( (array) $items as $id => $v ) {
				$v           = (array) $v;
				$variables[] = array(
					'id'     => (string) $id,
					'type'   => (string) $type,
					'label'  => $v['label'] ?? null,
					'value'  => $v['value'] ?? null,
					'status' => $v['status'] ?? 'active',
				);
			}
		}
		return array(
			'colors'    => $colors,
			'variables' => $variables,
		);
	}
}
