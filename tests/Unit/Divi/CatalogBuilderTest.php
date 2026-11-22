<?php
/**
 * CatalogBuilder reads a Divi theme folder the way the skill's generate_catalog.py does.
 *
 * @package WPDM
 */

use WPDM\Divi\CatalogBuilder;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class CatalogBuilderTest extends TestCase {

	/** @var string */
	private $dir;

	public function set_up() {
		parent::set_up();
		$this->dir = sys_get_temp_dir() . '/wpdm-theme-' . uniqid();
		$pkg       = $this->dir . '/includes/builder-5/visual-builder/packages/module-library/src/components';
		$this->write( 'style.css', "/*\nTheme Name: Divi\n  Version: 9.9.9 \n*/\n" );
		$this->write(
			substr( $pkg, strlen( $this->dir ) + 1 ) . '/zeta/module.json',
			json_encode(
				array(
					'_comment'     => 'x',
					'name'         => 'divi/zeta',
					'title'        => 'Zeta',
					'childrenName' => array( 'divi/zeta-item' ),
					'd4Shortcode'  => 'et_pb_zeta',
					'attributes'   => array(
						'module'  => array( 'settings' => array( 'decoration' => array( 'spacing' => array(), 'background' => array() ) ) ),
						'content' => array( 'settings' => array( 'innerContent' => array() ) ),
						'broken'  => 'not a dict',
					),
				)
			)
		);
		$this->write(
			substr( $pkg, strlen( $this->dir ) + 1 ) . '/alpha/module.json',
			json_encode(
				array(
					'name'       => 'divi/alpha',
					'category'   => 'child-module',
					'attributes' => array( 'title' => array( 'settings' => array( 'decoration' => array( 'font' => array() ) ) ) ),
				)
			)
		);
		$this->write(
			substr( $pkg, strlen( $this->dir ) + 1 ) . '/nameless/module.json',
			json_encode( array( 'attributes' => array( 'x' => array( 'settings' => array( 'decoration' => array( 'glow' => array() ) ) ) ) ) )
		);
		$this->write( substr( $pkg, strlen( $this->dir ) + 1 ) . '/bad/module.json', '{not json' );
	}

	public function tear_down() {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	/**
	 * @param string $rel     Relative path.
	 * @param string $content Content.
	 */
	private function write( $rel, $content ) {
		$path = $this->dir . '/' . $rel;
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}
		file_put_contents( $path, $content );
	}

	public function test_builds_catalog_shaped_like_module_catalog_json() {
		$this->assertSame(
			array(
				'decorationGroups' => array( 'background', 'font', 'glow', 'spacing' ),
				'diviVersion'      => '9.9.9',
				'modules'          => array(
					'divi/alpha' => array(
						'category'    => 'child-module',
						'children'    => array(),
						'd4Shortcode' => null,
						'elements'    => array( 'title' ),
						'title'       => null,
					),
					'divi/zeta'  => array(
						'category'    => 'module',
						'children'    => array( 'divi/zeta-item' ),
						'd4Shortcode' => 'et_pb_zeta',
						'elements'    => array( 'content', 'module' ),
						'title'       => 'Zeta',
					),
				),
			),
			CatalogBuilder::from_theme_dir( $this->dir )
		);
		$this->assertSame( '9.9.9', CatalogBuilder::theme_version( $this->dir ) );
	}

	public function test_rejects_folder_without_modules() {
		$this->expectException( RuntimeException::class );
		CatalogBuilder::from_theme_dir( $this->dir . '/includes' );
	}
}
