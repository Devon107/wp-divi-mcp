<?php
/**
 * divi_export_page.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Globals;
use WPDM\Divi\Inspector;
use WPDM\Divi\LayoutInput;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * A page as a Portability export with the globals it uses.
 */
final class ExportPage extends Tool {

	public function name(): string {
		return 'divi_export_page';
	}

	public function description(): string {
		return 'Export a page or post as a Divi Portability JSON: its stored Divi content, page CSS, and only the global colors/variables it references (images stay as URLs). Also returns a short outline (summary). Use it to study a site\'s real styles or to copy a page (pass portability to divi_apply_layout). Changes nothing.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'post_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page or post to export.',
				),
			),
			array( 'post_id' )
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = DiviSupport::editable_post( $args['post_id'] );

		$raw = (string) get_post_field( 'post_content', $post->ID, 'raw' );
		if ( false === strpos( $raw, '<!-- wp:divi/placeholder' ) ) {
			$raw = '<!-- wp:divi/placeholder -->' . $raw . '<!-- /wp:divi/placeholder -->';
		}
		$used = Inspector::globals( $raw );
		$used = array_flip( array_merge( $used['colors'], $used['variables'] ) );

		$globals   = Globals::read();
		$colors    = array();
		$variables = array();
		foreach ( (array) $globals['colors'] as $id => $c ) {
			$c = (array) $c;
			if ( isset( $used[ $id ] ) ) {
				$colors[] = array(
					(string) $id,
					array(
						'color'  => $c['color'] ?? '',
						'status' => $c['status'] ?? 'active',
						'label'  => $c['label'] ?? (string) $id,
					),
				);
			}
		}
		foreach ( (array) $globals['variables'] as $type => $items ) {
			foreach ( (array) $items as $id => $v ) {
				$v = (array) $v;
				if ( isset( $used[ $id ] ) ) {
					$variables[] = array(
						'id'     => (string) $id,
						'label'  => $v['label'] ?? (string) $id,
						'value'  => $v['value'] ?? '',
						'status' => $v['status'] ?? 'active',
						'type'   => (string) $type,
					);
				}
			}
		}

		$css = (string) get_post_meta( $post->ID, '_et_pb_custom_css', true );
		$doc = LayoutInput::envelope(
			$raw,
			(string) $post->ID,
			array(
				'global_colors'      => $colors,
				'global_variables'   => $variables,
				'page_settings_meta' => array( '_et_pb_custom_css' => array( $css ) ),
			)
		);

		return array(
			'post_id'          => $post->ID,
			'title'            => $post->post_title,
			'bytes'            => strlen( $raw ),
			'global_colors'    => count( $colors ),
			'global_variables' => count( $variables ),
			'portability'      => $doc,
			'summary'          => Inspector::tree( $raw, 3 ),
		);
	}
}
