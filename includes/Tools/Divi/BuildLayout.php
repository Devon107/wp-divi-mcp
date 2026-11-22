<?php
/**
 * divi_build_layout.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\LayoutInput;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a layout tree into Divi 5 markup and a Portability export. Writes nothing.
 */
final class BuildLayout extends Tool {

	public function name(): string {
		return 'divi_build_layout';
	}

	public function description(): string {
		return 'Build a Divi 5 layout tree (or check markup / a Portability export) into ready-to-import block markup and a Portability export, and validate it. Changes nothing on the site. Validation errors fail with validation_failed (data.report); tree mistakes fail with invalid_layout (data.path).';
	}

	public function input_schema(): array {
		return DiviSupport::schema( array(), array(), true );
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$resolved = LayoutInput::resolve( $args );
		return array(
			'markup'      => $resolved['content'],
			'portability' => $resolved['envelope'],
			'report'      => $resolved['report']->to_array(),
			'images'      => $resolved['images'],
			'bytes'       => strlen( $resolved['content'] ),
		);
	}
}
