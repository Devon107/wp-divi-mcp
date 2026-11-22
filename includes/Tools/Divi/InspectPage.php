<?php
/**
 * divi_inspect_page.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Globals;
use WPDM\Divi\Inspector;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only views of a page's Divi content.
 */
final class InspectPage extends Tool {

	const MODES = array( 'tree', 'section', 'example', 'paths', 'globals' );

	public function name(): string {
		return 'divi_inspect_page';
	}

	public function description(): string {
		return 'Look inside a page\'s Divi content without downloading it. mode=tree (default): outline of sections/rows/modules with text snippets; sections hidden on every device are marked [HIDDEN] (do not copy their styling). mode=section, n: full attrs of every block in top-level section n (1-based). mode=example, block, n: attrs of the first n instances of a block (e.g. divi/button). mode=paths, block?: every attribute path used per block type with a sample value. mode=globals: global colors/variables referenced, with their site values.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'post_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page or post.',
				),
				'mode'    => array(
					'type'        => 'string',
					'enum'        => self::MODES,
					'description' => 'tree (default), section, example, paths or globals.',
				),
				'n'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'section: section number (1-based, required). example: how many instances (default 1).',
				),
				'block'   => array(
					'type'        => 'string',
					'description' => 'example (required) and paths (optional): block name, e.g. "divi/text".',
				),
				'depth'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'tree: levels to show. Default 6.',
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
		$args    = PostSupport::validate( $this->input_schema(), $args );
		$post    = DiviSupport::editable_post( $args['post_id'] );
		$content = (string) get_post_field( 'post_content', $post->ID, 'raw' );
		$mode    = $args['mode'] ?? 'tree';
		$out     = array(
			'post_id' => $post->ID,
			'mode'    => $mode,
		);

		switch ( $mode ) {
			case 'section':
				if ( ! isset( $args['n'] ) ) {
					throw new ToolError( 'mode=section needs n (the section number, 1-based).', 'invalid_argument' );
				}
				try {
					$out['section'] = Inspector::section( $content, $args['n'] );
				} catch ( \OutOfRangeException $e ) {
					throw new ToolError( $e->getMessage(), 'invalid_argument' );
				}
				return $out;
			case 'example':
				if ( ! isset( $args['block'] ) ) {
					throw new ToolError( 'mode=example needs block, e.g. "divi/button".', 'invalid_argument' );
				}
				return array_merge( $out, array( 'block' => $args['block'] ), Inspector::example( $content, $args['block'], $args['n'] ?? 1 ) );
			case 'paths':
				$out['paths'] = (object) Inspector::paths( $content, $args['block'] ?? '' );
				return $out;
			case 'globals':
				return array_merge( $out, self::globals( $content ) );
			default:
				$out['tree'] = Inspector::tree( $content, min( 50, $args['depth'] ?? 6 ) );
				return $out;
		}
	}

	/**
	 * Referenced ids with their values on this site (null when missing).
	 *
	 * @param string $content Content.
	 * @return array
	 */
	private static function globals( string $content ): array {
		$used = Inspector::globals( $content );
		$site = Globals::read();
		$vars = array();
		foreach ( (array) $site['variables'] as $type => $items ) {
			foreach ( (array) $items as $id => $v ) {
				$vars[ $id ] = array_merge( (array) $v, array( 'type' => (string) $type ) );
			}
		}
		$colors = array();
		foreach ( $used['colors'] as $id ) {
			$c        = isset( $site['colors'][ $id ] ) ? (array) $site['colors'][ $id ] : null;
			$colors[] = array(
				'id'      => $id,
				'exists'  => null !== $c,
				'color'   => $c['color'] ?? null,
				'label'   => $c['label'] ?? null,
				'builtin' => in_array( $id, Globals::BUILTIN_COLORS, true ),
			);
		}
		$variables = array();
		foreach ( $used['variables'] as $id ) {
			$v           = $vars[ $id ] ?? null;
			$variables[] = array(
				'id'     => $id,
				'exists' => null !== $v,
				'type'   => $v['type'] ?? null,
				'value'  => $v['value'] ?? null,
				'label'  => $v['label'] ?? null,
			);
		}
		return array(
			'colors'    => $colors,
			'variables' => $variables,
		);
	}
}
