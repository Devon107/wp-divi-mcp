<?php
/**
 * Unit tests for the JSON layout tree (WPDM\Divi\LayoutTree) and its schema.
 *
 * @package WPDM
 */

use WPDM\Divi\Block;
use WPDM\Divi\D5;
use WPDM\Divi\LayoutError;
use WPDM\Divi\LayoutTree;
use WPDM\Divi\Patterns;
use WPDM\Divi\Theme;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class LayoutTreeTest extends TestCase {

	/**
	 * The spec's example tree (docs/superpowers/specs/...-design.md, "Layout input").
	 */
	const EXAMPLE = <<<'JSON'
{
  "builder_version": "5.14.0",
  "theme": {"accent": {"$color_var": "gcid-link-color"}, "card_radius": "16px"},
  "global_colors": [], "global_variables": [], "custom_css": "",
  "blocks": [
    {"section": {"css_id": "becas", "attrs": {}, "children": [
      {"row": {"children": [{"column": {"children": [
        {"pattern": "section_header", "args": {"eyebrow_label": "Becas", "title": "Una beca para cada talento",
                                               "intro": "Ayudas para estudiar con nosotros."}}
      ]}}]}},
      {"grid_row": {"cols": 3, "tablet": 2, "phone": 1, "children": [
        {"pattern": "icon_card", "args": {"icon_value": {"$fa": "&#xf19d;"}, "title": "Excelencia",
                                          "body": "…", "link_label": "Ver becas", "link_url": "/becas"}}
      ]}}
    ]}}
  ]
}
JSON;

	public function set_up() {
		parent::set_up();
		D5::set_builder_version( '5.19.1' );
	}

	public function tear_down() {
		D5::set_builder_version( '5.19.1' );
		parent::tear_down();
	}

	/**
	 * @param string $json JSON.
	 * @return array
	 */
	private static function tree( $json ) {
		$tree = json_decode( $json, true );
		if ( ! is_array( $tree ) ) {
			throw new RuntimeException( 'Bad test JSON: ' . json_last_error_msg() );
		}
		return $tree;
	}

	/**
	 * Wrap modules in section > row > column.
	 *
	 * @param array $nodes Nodes for the column.
	 * @return array
	 */
	private static function in_column( array $nodes ) {
		return array(
			'blocks' => array(
				array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( array( 'column' => array( 'children' => $nodes ) ) ) ) ) ) ) ),
			),
		);
	}

	/**
	 * Build and return the LayoutError (fails when none is thrown).
	 *
	 * @param array $tree Tree.
	 * @return LayoutError
	 */
	private function error_of( array $tree ) {
		try {
			LayoutTree::build( $tree );
		} catch ( LayoutError $e ) {
			$this->assertInstanceOf( InvalidArgumentException::class, $e );
			return $e;
		}
		$this->fail( 'Expected a LayoutError' );
	}

	public function test_tree_equals_direct_php_build() {
		$out = LayoutTree::build( self::tree( self::EXAMPLE ) );

		D5::set_builder_version( '5.14.0' );
		$theme  = Theme::from_array(
			array(
				'accent'      => D5::color_var( 'gcid-link-color' ),
				'card_radius' => '16px',
			)
		);
		$blocks = array(
			D5::section(
				D5::row( D5::column( ...Patterns::section_header( 'Becas', 'Una beca para cada talento', $theme, array( 'intro' => 'Ayudas para estudiar con nosotros.' ) ) ) ),
				D5::grid_row(
					Patterns::icon_card(
						D5::fa_icon( '&#xf19d;' ),
						'Excelencia',
						$theme,
						array(
							'body'       => '…',
							'link_label' => 'Ver becas',
							'link_url'   => '/becas',
						)
					),
					array(
						'cols'   => 3,
						'tablet' => 2,
						'phone'  => 1,
					)
				),
				array( 'css_id' => 'becas' )
			),
		);
		D5::set_builder_version( '5.19.1' );

		$this->assertEquals( $blocks, $out['blocks'] );
		$this->assertSame( D5::post_content( $blocks ), $out['content'] );
		$this->assertSame(
			D5::layout_export(
				$blocks,
				array(
					'global_colors'    => array(),
					'global_variables' => array(),
					'custom_css'       => '',
				)
			),
			$out['envelope']
		);
		$this->assertSame( array(), $out['images'] );
		$this->assertStringContainsString( '"builderVersion":"5.14.0"', $out['content'] );
	}

	public function test_spec_example_tree_builds() {
		$doc = file_get_contents( dirname( __DIR__, 3 ) . '/docs/superpowers/specs/2026-11-21-wp-divi-mcp-design.md' );
		$this->assertNotFalse( $doc );
		$section = substr( $doc, strpos( $doc, '### Layout input' ) );
		$this->assertSame( 1, preg_match( '/```json\n(.*?)\n```/s', $section, $m ) );
		$tree = self::tree( $m[1] );
		$out  = LayoutTree::build( $tree );
		$this->assertSame( 'divi/section', $out['blocks'][0]->name );
		$this->assertStringStartsWith( '<!-- wp:divi/placeholder -->', $out['content'] );
		$this->assertSame( 'et_builder', $out['envelope']['context'] );
		$this->assertStringContainsString( 'eyebrow_label', $m[1] );
		$this->assertStringContainsString( 'icon_value', $m[1] );
		$this->assertStringContainsString( '"$fa"', $m[1] );
		$this->assertStringContainsString( '"$color_var"', $m[1] );
	}

	public function test_unknown_helper_error_has_path() {
		$tree = self::in_column( array( array( 'text' => array( 'html' => '<p>a</p>' ) ), array( 'txet' => array( 'html' => '<p>b</p>' ) ) ) );
		$e    = $this->error_of( $tree );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].column.children[1]', $e->path() );
		$this->assertStringContainsString( "'txet'", $e->getMessage() );
		$this->assertStringContainsString( "'text'", $e->getMessage() ); // Did you mean.
		$this->assertStringStartsWith( $e->path() . ': ', $e->getMessage() );
	}

	public function test_unknown_pattern_and_pattern_used_as_helper() {
		$e = $this->error_of( self::in_column( array( array( 'icon_card' => array( 'title' => 'x' ) ) ) ) );
		$this->assertStringContainsString( '{"pattern": "icon_card"', $e->getMessage() );

		$e = $this->error_of( self::in_column( array( array( 'pattern' => 'icon_crad' ) ) ) );
		$this->assertStringContainsString( "unknown pattern 'icon_crad'", $e->getMessage() );
		$this->assertStringContainsString( "'icon_card'", $e->getMessage() );
	}

	public function test_card_pattern_inside_column_rejected() {
		$card = array(
			'pattern' => 'stat_card',
			'args'    => array(
				'number' => '98%',
				'label'  => 'Aprobados',
			),
		);
		$e    = $this->error_of( self::in_column( array( $card ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].column.children[0].pattern(stat_card)', $e->path() );
		$this->assertStringContainsString( 'row or grid_row', $e->getMessage() );

		$e = $this->error_of( self::in_column( array( array( 'group' => array( 'children' => array( $card ) ) ) ) ) );
		$this->assertStringContainsString( 'group', $e->getMessage() );

		// Directly in a row it is fine.
		$out = LayoutTree::build(
			array( 'blocks' => array( array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( $card ) ) ) ) ) ) ) )
		);
		$this->assertSame( 'divi/column', $out['blocks'][0]->children[0]->children[0]->name );
	}

	public function test_row_outside_section_rejected() {
		$e = $this->error_of( self::in_column( array( array( 'row' => array( 'children' => array( array( 'column' => new stdClass() ) ) ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].column.children[0].row', $e->path() );
		$this->assertStringContainsString( 'only allowed directly inside a section', $e->getMessage() );
		$this->assertStringContainsString( 'column', $e->getMessage() );
	}

	public function test_structure_rules() {
		// Top level must be sections.
		$e = $this->error_of( array( 'blocks' => array( array( 'text' => array( 'html' => 'x' ) ) ) ) );
		$this->assertSame( 'blocks[0].text', $e->path() );
		$this->assertStringContainsString( 'must be sections', $e->getMessage() );

		// Column outside a row.
		$e = $this->error_of( array( 'blocks' => array( array( 'section' => array( 'children' => array( array( 'column' => new stdClass() ) ) ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].column', $e->path() );

		// Module directly in a row.
		$e = $this->error_of( array( 'blocks' => array( array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( array( 'text' => array( 'html' => 'x' ) ) ) ) ) ) ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].text', $e->path() );
		$this->assertStringContainsString( 'Wrap it in a column', $e->getMessage() );

		// Nested section.
		$e = $this->error_of( self::in_column( array( array( 'section' => new stdClass() ) ) ) );
		$this->assertStringContainsString( 'top level', $e->getMessage() );
	}

	public function test_missing_required_arg_has_path() {
		$tree = array(
			'blocks' => array(
				array(
					'section' => array(
						'children' => array(
							array(
								'grid_row' => array(
									'children' => array(
										array( 'column' => new stdClass() ),
										array(
											'pattern' => 'icon_card',
											'args'    => array( 'title' => 'Sin icono' ),
										),
									),
								),
							),
						),
					),
				),
			),
		);
		$e    = $this->error_of( $tree );
		$this->assertSame( 'blocks[0].section.children[0].grid_row.children[1].pattern(icon_card).args.icon_value', $e->path() );
		$this->assertStringContainsString( "missing required arg 'icon_value'", $e->getMessage() );
		$this->assertStringContainsString( 'icon_value, title', $e->getMessage() );

		$e = $this->error_of( self::in_column( array( array( 'heading' => array( 'level' => 'h1' ) ) ) ) );
		$this->assertStringEndsWith( '.column.children[0].heading.title', $e->path() );
	}

	public function test_d5_errors_wrapped_with_path_and_allowed_args() {
		$e = $this->error_of( self::in_column( array( array( 'heading' => array( 'title' => 'x', 'colour' => 'red' ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].column.children[0].heading', $e->path() );
		$this->assertStringContainsString( "unknown arg 'colour'", $e->getMessage() );
		$this->assertStringContainsString( 'Allowed args: title, level, attrs', $e->getMessage() );

		// D5 exceptions are wrapped: path prefixed, original kept as previous.
		$e = $this->error_of( array( 'blocks' => array( array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( array( 'column' => array( 'width' => 'half' ) ) ) ) ) ) ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row.children[0].column', $e->path() );
		$this->assertStringContainsString( 'Invalid column width', $e->getMessage() );
		$this->assertInstanceOf( InvalidArgumentException::class, $e->getPrevious() );

		$e = $this->error_of( self::in_column( array( array( 'icon' => array( 'icon' => array( '$fa' => 'x' ) ) ) ) ) );
		$this->assertStringContainsString( "Did you mean 'icon_value'?", $e->getMessage() );

		$e = $this->error_of( self::in_column( array( array( 'text' => array( 'html' => 'x', 'children' => array() ) ) ) ) );
		$this->assertStringContainsString( 'does not take children', $e->getMessage() );

		// Row widths error from D5::row() gets the row path.
		$col = array( 'column' => array( 'width' => '1_2' ) );
		$e   = $this->error_of( array( 'blocks' => array( array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( $col ) ) ) ) ) ) ) ) );
		$this->assertSame( 'blocks[0].section.children[0].row', $e->path() );
		$this->assertStringContainsString( 'add up to 1/2', $e->getMessage() );
	}

	public function test_literal_wrapper() {
		$attrs = array(
			'module' => array(
				'meta' => array(
					'$literal' => array( 'x' => array( '$rv' => 'kept' ) ),
				),
			),
		);
		$out   = LayoutTree::build( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => $attrs ) ) ) ) );
		$code  = $out['blocks'][0]->children[0]->children[0]->children[0];
		// Nothing inside $literal is resolved, not even a "$rv" key.
		$this->assertSame( array( 'x' => array( '$rv' => 'kept' ) ), $code->attrs['module']['meta'] );

		// $literal must be alone in its object.
		$attrs['module']['meta']['other'] = 1;
		$e                                = $this->error_of( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => $attrs ) ) ) ) );
		$this->assertStringEndsWith( '.code.attrs.module.meta.$literal', $e->path() );
		$this->assertStringContainsString( 'only key', $e->getMessage() );
	}

	public function test_real_divi_attr_keys_pass_through() {
		// Shapes copied from a real Divi 5 export: keys named like the helpers, without "$".
		$attrs = array(
			'title'  => array(
				'decoration' => array(
					'font' => array( 'font' => array( 'desktop' => array( 'value' => array( 'size' => '32px', 'color' => '#161834' ) ) ) ),
				),
			),
			'module' => array(
				'decoration' => array(
					'border'  => array(
						'desktop' => array(
							'value' => array(
								'radius' => array( 'topLeft' => '16px', 'topRight' => '16px', 'bottomLeft' => '16px', 'bottomRight' => '16px', 'sync' => 'on' ),
							),
						),
					),
					'layout'  => array( 'desktop' => array( 'value' => array( 'display' => 'flex', 'flex' => '1', 'grid' => 'x' ) ) ),
					'spacing' => array( 'desktop' => array( 'value' => array( 'padding' => array( 'box' => '1', 'card' => '2' ) ) ) ),
				),
			),
		);
		$out   = LayoutTree::build( self::in_column( array( array( 'heading' => array( 'title' => 'Hi', 'attrs' => $attrs ) ) ) ) );
		$h     = $out['blocks'][0]->children[0]->children[0]->children[0];
		$this->assertSame( $attrs['module'], $h->attrs['module'] );
		$value = $h->attrs['title']['decoration']['font']['font']['desktop']['value'];
		$this->assertSame( '32px', $value['size'] );
		$this->assertSame( '#161834', $value['color'] );
		$this->assertSame( 'h2', $value['headingLevel'] ); // Merged in by heading() itself.

		// $-helpers still resolve inside real shapes, and an unknown $name is an error.
		$attrs['title']['decoration']['font']['font']['desktop']['value']['color'] = array( '$color_var' => 'gcid-heading' );
		$out = LayoutTree::build( self::in_column( array( array( 'heading' => array( 'title' => 'Hi', 'attrs' => $attrs ) ) ) ) );
		$h   = $out['blocks'][0]->children[0]->children[0]->children[0];
		$this->assertSame( D5::color_var( 'gcid-heading' ), $h->attrs['title']['decoration']['font']['font']['desktop']['value']['color'] );

		$attrs['title']['decoration']['font']['font']['desktop']['value']['color'] = array( '$colour_var' => 'gcid-heading' );
		$e = $this->error_of( self::in_column( array( array( 'heading' => array( 'title' => 'Hi', 'attrs' => $attrs ) ) ) ) );
		$this->assertStringEndsWith( '.value.color.$colour_var', $e->path() );
		$this->assertStringContainsString( "Did you mean '\$color_var'?", $e->getMessage() );
	}

	public function test_merge_helper() {
		$attrs = array(
			'$merge' => array(
				array( '$font' => array( 'element' => 'title', 'desktop' => array( 'size' => '32px' ) ) ),
				array( '$module_deco' => array( 'sizing' => array( '$rv' => array( 'desktop' => array( 'maxWidth' => '700px' ) ) ) ) ),
				null,
				array( 'title' => array( 'decoration' => array( 'font' => array( 'font' => array( 'desktop' => array( 'value' => array( 'color' => '#000' ) ) ) ) ) ) ),
			),
		);
		$out   = LayoutTree::build( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => $attrs ) ) ) ) );
		$got   = $out['blocks'][0]->children[0]->children[0]->children[0]->attrs;
		$want  = D5::merge(
			array( 'content' => array( 'innerContent' => D5::rv( 'x' ) ) ),
			D5::merge(
				D5::font( 'title', array( 'desktop' => array( 'size' => '32px' ) ) ),
				D5::module_deco( array( 'sizing' => D5::rv( array( 'maxWidth' => '700px' ) ) ) ),
				null,
				array( 'title' => array( 'decoration' => array( 'font' => array( 'font' => array( 'desktop' => array( 'value' => array( 'color' => '#000' ) ) ) ) ) ) )
			)
		);
		unset( $got['builderVersion'] );
		$this->assertEquals( $want, $got );
		$this->assertSame( array( 'size' => '32px', 'color' => '#000' ), $got['title']['decoration']['font']['font']['desktop']['value'] );

		$e = $this->error_of( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => array( '$merge' => array( array( 'a' => 1 ), 'oops' ) ) ) ) ) ) );
		$this->assertStringEndsWith( '.code.attrs.$merge[1]', $e->path() );
		$this->assertStringContainsString( 'list of objects', $e->getMessage() );

		$e = $this->error_of( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => array( '$merge' => array( 'a' => 1 ) ) ) ) ) ) );
		$this->assertStringEndsWith( '.code.attrs.$merge', $e->path() );
	}

	public function test_helper_detection_respects_sibling_keys() {
		$out  = LayoutTree::build(
			self::in_column(
				array(
					array( 'icon' => array( 'icon_value' => array( '$fa' => '&#xf0e0;', 'weight' => '400' ) ) ),
					array(
						'text' => array(
							'html'  => 'x',
							'attrs' => array(
								'a' => array( '$fa' => '&#xf0e0;', 'size' => '12px' ),
								'b' => array( '$color_var' => 'gcid-x', 'opacity' => 50 ),
								'c' => array( '$rv' => array( 'desktop' => '1px' ), 'extra' => 1 ),
								'd' => array( '$box' => array( '1px' ), '$radius' => '2px' ),
							),
						),
					),
				)
			)
		);
		$col  = $out['blocks'][0]->children[0]->children[0];
		$icon = $col->children[0];
		$this->assertSame( D5::fa_icon( '&#xf0e0;', '400' ), $icon->attrs['icon']['innerContent']['desktop']['value'] );
		$attrs = $col->children[1]->attrs;
		// fa + an unknown sibling: plain object.
		$this->assertSame( array( '$fa' => '&#xf0e0;', 'size' => '12px' ), $attrs['a'] );
		// color_var + its opacity sibling: helper.
		$this->assertSame( D5::color_var( 'gcid-x', 50 ), $attrs['b'] );
		// rv + unknown sibling: plain object, but rv inside is not a helper call either (it is a key).
		$this->assertSame( array( '$rv' => array( 'desktop' => '1px' ), 'extra' => 1 ), $attrs['c'] );
		// Two helper keys: plain object.
		$this->assertSame( array( '$box' => array( '1px' ), '$radius' => '2px' ), $attrs['d'] );
	}

	public function test_value_helpers_resolve() {
		$attrs = array(
			'rv'       => array( '$rv' => array( 'desktop' => '1px', 'tablet' => '2px', 'hover' => '3px', 'phoneWide' => '4px' ) ),
			'box1'     => array( '$box' => array( '10px' ) ),
			'box2'     => array( '$box' => array( '10px', '20px' ) ),
			'box3'     => array( '$box' => array( '1px', '2px', '3px' ) ),
			'box4'     => array( '$box' => array( '1px', '2px', '3px', '4px' ) ),
			'boxo'     => array( '$box' => array( 'top' => '5px' ) ),
			'rad1'     => array( '$radius' => '8px' ),
			'rad4'     => array( '$radius' => array( '1px', '2px', '3px', '4px' ) ),
			'cv'       => array( '$color_var' => array( 'id' => 'gcid-a', 'opacity' => 40 ) ),
			'cv2'      => array( '$color_var' => 'gcid-b' ),
			'gv'       => array( '$content_var' => 'gvid-c' ),
			'di'       => array( '$divi_icon' => '&#xe00b;' ),
			'fa'       => array( '$fa' => '&#xf19d;' ),
			'font'     => array( '$font' => array( 'element' => 'title', 'desktop' => array( 'color' => array( '$color_var' => 'gcid-a' ) ) ) ),
			'flex'     => array( '$flex' => array( 'direction' => 'column', 'gap' => '8px' ) ),
			'grid'     => array( '$grid' => array( 'columns' => 3 ) ),
			'card'     => array( '$card' => array( 'background' => '#fff', 'radius' => array( '1px', '2px', '3px', '4px' ) ) ),
			'deco'     => array( '$module_deco' => array( 'sizing' => array( '$rv' => array( 'desktop' => array( 'maxWidth' => '700px' ) ) ) ) ),
			'nested'   => array( array( '$rv' => array( 'desktop' => array( '$color_var' => 'gcid-a' ) ) ) ),
			'emptyobj' => new stdClass(),
		);
		$out   = LayoutTree::build( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => $attrs ) ) ) ) );
		$got   = $out['blocks'][0]->children[0]->children[0]->children[0]->attrs;
		$this->assertEquals( D5::rv( '1px', '2px', null, array( 'hover' => '3px', 'phoneWide' => '4px' ) ), $got['rv'] );
		$this->assertSame( D5::box( '10px', '10px', '10px', '10px' ), $got['box1'] );
		$this->assertSame( D5::box( '10px', '20px', '10px', '20px' ), $got['box2'] );
		$this->assertSame( D5::box( '1px', '2px', '3px', '2px' ), $got['box3'] );
		$this->assertSame( D5::box( '1px', '2px', '3px', '4px' ), $got['box4'] );
		$this->assertSame( D5::box( '5px' ), $got['boxo'] );
		$this->assertSame( D5::radius( '8px' ), $got['rad1'] );
		$this->assertSame( D5::radius( '1px', '2px', '3px', '4px' ), $got['rad4'] );
		$this->assertSame( D5::color_var( 'gcid-a', 40 ), $got['cv'] );
		$this->assertSame( D5::color_var( 'gcid-b' ), $got['cv2'] );
		$this->assertSame( D5::content_var( 'gvid-c' ), $got['gv'] );
		$this->assertSame( D5::divi_icon( '&#xe00b;' ), $got['di'] );
		$this->assertSame( D5::fa_icon( '&#xf19d;' ), $got['fa'] );
		$this->assertEquals( D5::font( 'title', array( 'desktop' => array( 'color' => D5::color_var( 'gcid-a' ) ) ) ), $got['font'] );
		$this->assertEquals( D5::flex( array( 'direction' => 'column', 'gap' => '8px' ) ), $got['flex'] );
		$this->assertSame( D5::grid( array( 'columns' => 3 ) ), $got['grid'] );
		$this->assertEquals( D5::card( array( 'background' => '#fff', 'radius' => array( '1px', '2px', '3px', '4px' ) ) ), $got['card'] );
		$this->assertEquals( D5::module_deco( array( 'sizing' => D5::rv( array( 'maxWidth' => '700px' ) ) ) ), $got['deco'] );
		$this->assertEquals( array( D5::rv( D5::color_var( 'gcid-a' ) ) ), $got['nested'] );
		$this->assertEquals( new stdClass(), $got['emptyobj'] );
	}

	public function test_bad_value_helper_shapes_have_paths() {
		$cases = array(
			array( array( '$rv' => '16px' ), 'rv', 'expects an object' ),
			array( array( '$rv' => array( 'color' => '#fff' ) ), 'rv', "unknown arg 'color'" ),
			array( array( '$box' => array( '1', '2', '3', '4', '5' ) ), 'box', '1 to 4' ),
			array( array( '$radius' => array( '1', '2' ) ), 'radius', '1 or 4' ),
			array( array( '$color_var' => 42 ), 'color_var', 'gcid' ),
			array( array( '$flex' => array( 'direktion' => 'row' ) ), 'flex', "unknown arg 'direktion'" ),
		);
		foreach ( $cases as $case ) {
			list( $value, $helper, $needle ) = $case;
			$e = $this->error_of( self::in_column( array( array( 'code' => array( 'html' => 'x', 'attrs' => array( 'k' => $value ) ) ) ) ) );
			$this->assertStringEndsWith( '.code.attrs.k.$' . $helper, $e->path(), $helper );
			$this->assertStringContainsString( $needle, $e->getMessage(), $helper );
		}
	}

	public function test_builder_version_and_theme_applied() {
		$tree = self::in_column(
			array(
				array(
					'pattern' => 'heading_text',
					'args'    => array( 'title' => 'A' ),
				),
				array(
					'pattern' => 'heading_text',
					'args'    => array(
						'title' => 'B',
						'theme' => array( 'h2_size' => '50px' ),
					),
				),
			)
		);
		$tree['builder_version'] = '5.14.0';
		$tree['theme']           = array(
			'heading' => array( '$color_var' => 'gcid-heading' ),
			'h2_size' => '40px',
		);
		$out                     = LayoutTree::build( $tree );
		$col                     = $out['blocks'][0]->children[0]->children[0];

		$top = Theme::from_array(
			array(
				'heading' => D5::color_var( 'gcid-heading' ),
				'h2_size' => '40px',
			)
		);
		$own = Theme::from_array(
			array(
				'heading' => D5::color_var( 'gcid-heading' ),
				'h2_size' => '50px',
			)
		);
		D5::set_builder_version( '5.14.0' );
		$this->assertEquals( Patterns::heading_text( 'A', $top ), $col->children[0] );
		$this->assertEquals( Patterns::heading_text( 'B', $own ), $col->children[1] );
		$this->assertSame( '5.14.0', $col->attrs['builderVersion'] );

		$tree['theme'] = array( 'accent_colour' => '#000' );
		$e             = $this->error_of( $tree );
		$this->assertSame( 'theme', $e->path() );
		$this->assertStringContainsString( 'accent_colour', $e->getMessage() );
	}

	public function test_builder_version_restored_after_build() {
		D5::set_builder_version( '5.0.0' );
		$tree                    = self::in_column( array( array( 'text' => array( 'html' => 'x' ) ) ) );
		$tree['builder_version'] = '5.14.0';
		LayoutTree::build( $tree );
		$this->assertSame( '5.0.0', D5::builder_version() );

		$tree['blocks'][0]['section']['children'][0]['row']['children'][0]['column']['children'][] = array( 'nope' => array() );
		try {
			LayoutTree::build( $tree );
			$this->fail( 'Expected a LayoutError' );
		} catch ( LayoutError $e ) {
			$this->assertSame( '5.0.0', D5::builder_version() );
		}
	}

	public function test_block_escape_hatch_and_outputs() {
		$tree = array(
			'global_colors' => array( D5::global_color( 'gcid-a', '#123456', 'A' ) ),
			'custom_css'    => '.x{}',
			'blocks'        => array(
				array(
					'block'    => 'divi/section',
					'children' => array(
						array(
							'row' => array(
								'children' => array(
									array(
										'column' => array(
											'children' => array(
												array(
													'block' => 'divi/image',
													'attrs' => array( 'image' => array( 'innerContent' => array( '$rv' => array( 'desktop' => array( 'src' => 'https://ex.com/a.png' ) ) ) ) ),
												),
											),
										),
									),
								),
							),
						),
					),
				),
			),
		);
		$out  = LayoutTree::build( $tree );
		$img  = $out['blocks'][0]->children[0]->children[0]->children[0];
		$this->assertInstanceOf( Block::class, $img );
		$this->assertSame( 'divi/image', $img->name );
		$this->assertNull( $img->children );
		$this->assertSame( array( 'https://ex.com/a.png' ), $out['images'] );
		$this->assertSame( array( D5::global_color( 'gcid-a', '#123456', 'A' ) ), $out['envelope']['global_colors'] );
		$this->assertSame( array( '.x{}' ), $out['envelope']['page_settings_meta']['_et_pb_custom_css'] );

		$tree['blocks'][0]['block'] = 'section';
		$e                          = $this->error_of( $tree );
		$this->assertSame( 'blocks[0].block(section)', $e->path() );
		$this->assertStringContainsString( 'divi/', $e->getMessage() );
	}

	public function test_top_level_validation() {
		$e = $this->error_of( array( 'blocks' => array(), 'colors' => array() ) );
		$this->assertSame( 'colors', $e->path() );
		$this->assertStringContainsString( 'unknown top-level key', $e->getMessage() );

		$e = $this->error_of( array( 'theme' => array() ) );
		$this->assertSame( 'blocks', $e->path() );

		$e = $this->error_of( array( 'blocks' => array( 'section' => array() ) ) );
		$this->assertSame( 'blocks', $e->path() );
		$this->assertStringContainsString( 'list', $e->getMessage() );
	}

	public function test_accepts_object_decoded_json() {
		$assoc   = LayoutTree::build( self::tree( self::EXAMPLE ) );
		$objects = LayoutTree::build( (array) json_decode( self::EXAMPLE ) );
		$this->assertSame( $assoc['content'], $objects['content'] );
	}

	public function test_schema_is_valid_json_and_lists_all_helpers() {
		$file = dirname( __DIR__, 3 ) . '/includes/Divi/schema/layout.schema.json';
		$raw  = file_get_contents( $file );
		$this->assertNotFalse( $raw );
		$this->assertLessThan( 60 * 1024, strlen( $raw ) );
		$schema = json_decode( $raw, true );
		$this->assertIsArray( $schema, json_last_error_msg() );
		$this->assertSame( $schema, LayoutTree::schema() );
		$this->assertSame( 'https://json-schema.org/draft/2020-12/schema', $schema['$schema'] );

		$defs     = $schema['$defs'];
		$helpers  = array();
		$patterns = array();
		$values   = array();
		foreach ( array_keys( $defs ) as $key ) {
			if ( 0 === strpos( $key, 'node_' ) ) {
				$helpers[] = substr( $key, 5 );
			} elseif ( 0 === strpos( $key, 'pattern_' ) ) {
				$patterns[] = substr( $key, 8 );
			} elseif ( 0 === strpos( $key, 'value_' ) ) {
				$values[] = substr( $key, 6 );
			}
		}
		$this->assertSame( LayoutTree::helpers(), $helpers );
		$this->assertSame( LayoutTree::patterns(), $patterns );
		$this->assertSame( array_merge( LayoutTree::value_helpers(), array( 'literal' ) ), $values );
		$this->assertArrayHasKey( 'block', $defs );

		// Every helper/pattern exists, and the schema's required args are the PHP required params.
		foreach ( LayoutTree::helpers() as $name ) {
			$m        = new ReflectionMethod( D5::class, $name );
			$required = array();
			foreach ( $m->getParameters() as $p ) {
				if ( ! $p->isOptional() ) {
					$required[] = $p->getName();
				}
			}
			$this->assertSame( $required, $defs[ 'node_' . $name ]['properties'][ $name ]['required'] ?? array(), $name );
		}
		foreach ( LayoutTree::patterns() as $name ) {
			$m        = new ReflectionMethod( Patterns::class, $name );
			$required = array();
			foreach ( $m->getParameters() as $p ) {
				if ( ! $p->isOptional() ) {
					$required[] = $p->getName();
				}
			}
			$this->assertSame( $required, $defs[ 'pattern_' . $name ]['properties']['args']['required'], $name );
		}
		$this->assertSame(
			array( 'eyebrow', 'heading_text', 'body_text', 'section_header', 'icon_badge', 'number_badge', 'pill_link', 'icon_card', 'media_card', 'person_card', 'testimonial_card', 'numbered_card', 'step_card', 'stat_card' ),
			LayoutTree::patterns()
		);
	}

	public function test_schema_matches_generator() {
		require_once dirname( __DIR__, 3 ) . '/tools/gen-layout-schema.php';
		$generated = json_decode( wpdm_layout_schema_json(), true );
		$this->assertSame( $generated, LayoutTree::schema(), 'Schema is stale: run tools/gen-layout-schema.php' );
	}
}
