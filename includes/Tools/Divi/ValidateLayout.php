<?php
/**
 * divi_validate_layout.
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
 * Validates a layout and returns the report, errors included. Writes nothing.
 */
final class ValidateLayout extends Tool {

	public function name(): string {
		return 'divi_validate_layout';
	}

	public function description(): string {
		return 'Validate a Divi 5 layout (tree, block markup or Portability export) against the module catalog: structure, attributes, contrast, globals. Returns {ok, report: {errors, warnings, info, counts}}; ok=false means divi_apply_layout would refuse it. Global ids that already exist on this site are not reported as missing. Changes nothing.';
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
		$resolved = LayoutInput::resolve( $args, false );
		$report   = $resolved['report']->to_array();
		return array(
			'ok'     => $report['ok'],
			'source' => $resolved['source'],
			'report' => $report,
			'bytes'  => strlen( $resolved['content'] ),
		);
	}
}
