<?php
/**
 * Smoke test for plugin bootstrap.
 *
 * @package WPDM
 */

use WPDM\Plugin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PluginTest extends TestCase {

	public function test_plugin_boots() {
		$this->assertTrue( defined( 'WPDM_VERSION' ) );
		$this->assertSame( '0.2.0', WPDM_VERSION );
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}
}
