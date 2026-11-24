<?php
/**
 * Unit tests for WPDM\I18n\Protect (protected parts of html segments).
 *
 * @package WPDM
 */

use WPDM\I18n\Protect;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ProtectTest extends TestCase {

	const HTML = '<style>.a{}</style><div><p>Hi [btn x="1"]</p></div><script>x()</script><!-- c -->';

	public function test_mask_unmask_roundtrip() {
		$m = Protect::mask( self::HTML );
		$this->assertSame( array( '<style>.a{}</style>', '[btn x="1"]', '<script>x()</script>', '<!-- c -->' ), $m['parts'] );
		$this->assertSame( '⟦1⟧<div><p>Hi ⟦2⟧</p></div>⟦3⟧⟦4⟧', $m['text'] );
		$this->assertSame( self::HTML, Protect::unmask( $m['text'], $m['parts'] ) );
	}

	public function test_unmask_missing_marker_null() {
		$m = Protect::mask( self::HTML );
		$this->assertNull( Protect::unmask( '⟦1⟧<div><p>Salut</p></div>⟦3⟧⟦4⟧', $m['parts'] ) );
	}

	public function test_unmask_duplicated_or_unknown_marker_null() {
		$m = Protect::mask( self::HTML );
		$this->assertNull( Protect::unmask( '⟦1⟧<div><p>Salut ⟦2⟧ ⟦2⟧</p></div>⟦3⟧⟦4⟧', $m['parts'] ) );
		$this->assertNull( Protect::unmask( '⟦1⟧<div><p>Salut ⟦2⟧ ⟦5⟧</p></div>⟦3⟧⟦4⟧', $m['parts'] ) );
		$this->assertNull( Protect::unmask( '⟦01⟧<div><p>Salut ⟦2⟧</p></div>⟦3⟧⟦4⟧', $m['parts'] ) );
	}

	public function test_unmask_allows_reordered_markers() {
		$m = Protect::mask( '<p>[a] and [b]</p>' );
		$this->assertSame( '<p>⟦1⟧ and ⟦2⟧</p>', $m['text'] );
		$this->assertSame( '<p>[b] et [a]</p>', Protect::unmask( '<p>⟦2⟧ et ⟦1⟧</p>', $m['parts'] ) );
	}

	public function test_tags_sequence() {
		$m = Protect::mask( self::HTML );
		$this->assertSame( array( 'div', 'p', '/p', '/div' ), Protect::tags( $m['text'] ) );
		$this->assertSame( array( 'p', 'br', 'a', '/a', '/p' ), Protect::tags( '<P class="x">a<br />b <a href="/x">c</a> 1 < 2</p>' ) );
	}

	public function test_other_protected_parts() {
		$html = "<?php echo 1; ?><SCRIPT type=\"a\">if(a<b){}</SCRIPT >[/btn] ⟦7⟧ [1] <style>unclosed";
		$m    = Protect::mask( $html );
		$this->assertSame( array( '<?php echo 1; ?>', "<SCRIPT type=\"a\">if(a<b){}</SCRIPT >", '[/btn]', '⟦7⟧', '<style>unclosed' ), $m['parts'] );
		$this->assertSame( '⟦1⟧⟦2⟧⟦3⟧ ⟦4⟧ [1] ⟦5⟧', $m['text'] );
		$this->assertSame( $html, Protect::unmask( $m['text'], $m['parts'] ) );
	}

	public function test_no_parts() {
		$m = Protect::mask( '<p>Plain</p>' );
		$this->assertSame( array(), $m['parts'] );
		$this->assertSame( '<p>Plain</p>', Protect::unmask( '<p>Plain</p>', array() ) );
		$this->assertNull( Protect::unmask( '<p>⟦1⟧</p>', array() ) );
	}
}
