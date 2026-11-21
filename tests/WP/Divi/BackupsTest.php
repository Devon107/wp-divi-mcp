<?php
/**
 * Backups store (private wpdm_backup CPT) and Env without Divi.
 *
 * @package WPDM
 */

use WPDM\Divi\Backups;
use WPDM\Divi\Env;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

/**
 * Runs in the non-Divi suite.
 */
class BackupsTest extends WP_UnitTestCase {

	/**
	 * Divi markup with quote escapes, backslashes and unicode: must round-trip byte-exact.
	 */
	const DIVI_MARKUP = '<!-- wp:divi/section {"module":{"meta":{"adminLabel":{"desktop":{"value":"Hero \"main\""}}}},"builderVersion":"5.19.1"} --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>C:\\\\path\\\\to \\\\n not a newline &amp; \\u00e9 été</p>"}}}} /--><!-- /wp:divi/section -->';

	/** @var int */
	private $admin;
	/** @var int */
	private $editor;

	public function set_up() {
		parent::set_up();
		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->admin );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_save_list_get() {
		$id = Backups::save( 'post_content', 42, self::DIVI_MARKUP, array( 'title' => 'Home "page"' ), 'Laptop' );
		$this->assertGreaterThan( 0, $id );

		$post = get_post( $id );
		$this->assertSame( Backups::POST_TYPE, $post->post_type );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( self::DIVI_MARKUP, $post->post_content, 'post_content must be byte-exact' );

		$backup = Backups::get( $id );
		$this->assertSame( self::DIVI_MARKUP, $backup['content'] );
		$this->assertSame( $id, $backup['id'] );
		$this->assertSame( 'post_content', $backup['kind'] );
		$this->assertSame( 42, $backup['source_id'] );
		$this->assertSame( 'Laptop', $backup['connection'] );
		$this->assertSame( array( 'title' => 'Home "page"' ), $backup['meta'] );
		$this->assertSame( strlen( self::DIVI_MARKUP ), $backup['bytes'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $backup['created'] );

		$this->assertSame( 'post_content', get_post_meta( $id, '_wpdm_kind', true ) );
		$this->assertSame( '42', get_post_meta( $id, '_wpdm_source', true ) );
		$this->assertSame( 'Laptop', get_post_meta( $id, '_wpdm_connection', true ) );
		$this->assertSame( '{"title":"Home \"page\""}', get_post_meta( $id, '_wpdm_meta', true ) );

		$globals = Backups::save( 'globals', 0, '{"colors":{},"variables":{}}', array(), '' );
		$other   = Backups::save( 'post_content', 7, 'x', array(), '' );

		$all = Backups::list();
		$this->assertSame( array( $other, $globals, $id ), wp_list_pluck( $all, 'id' ), 'newest first' );
		$this->assertArrayNotHasKey( 'content', $all[0], 'list omits content' );

		$this->assertSame( array( $id ), wp_list_pluck( Backups::list( 42 ), 'id' ) );
		$this->assertSame( array( $globals ), wp_list_pluck( Backups::list( null, 'globals' ), 'id' ) );
		$this->assertSame( array( $globals ), wp_list_pluck( Backups::list( 0, 'globals' ), 'id' ) );
		$this->assertSame( array( $other ), wp_list_pluck( Backups::list( null, null, 1 ), 'id' ) );

		$this->assertNull( Backups::get( 999999 ) );
		$plain = self::factory()->post->create();
		$this->assertNull( Backups::get( $plain ), 'get() only returns backups' );

		try {
			Backups::save( 'nope', 1, 'x', array(), '' );
			$this->fail( 'Expected ToolError for an unknown kind' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_backup_kind', $e->error_code() );
		}
	}

	public function test_save_is_byte_exact_for_users_without_unfiltered_html() {
		wp_set_current_user( $this->editor );
		// Editors keep unfiltered_html on single site; take it away to force kses on.
		$filter = static function ( $caps, $cap ) {
			return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $filter, 10, 2 );
		kses_init();
		try {
			$content = '<script>alert(1)</script>' . self::DIVI_MARKUP;
			$id      = Backups::save( 'post_content', 3, $content, array(), '' );
			$this->assertSame( $content, get_post( $id )->post_content );
			$this->assertTrue( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) !== false, 'kses filters are restored' );
		} finally {
			remove_filter( 'map_meta_cap', $filter, 10 );
			kses_remove_filters();
		}
	}

	public function test_prune_keeps_20() {
		$ids = array();
		for ( $i = 0; $i < 23; $i++ ) {
			$ids[] = Backups::save( 'post_content', 5, 'v' . $i, array(), '' );
		}
		$keep_other_kind   = Backups::save( 'globals', 5, '{}', array(), '' );
		$keep_other_source = Backups::save( 'post_content', 6, 'other', array(), '' );

		$listed = wp_list_pluck( Backups::list( 5, 'post_content', 100 ), 'id' );
		$this->assertCount( 20, $listed );
		$this->assertSame( array_reverse( array_slice( $ids, 3 ) ), $listed, 'the newest 20 are kept' );
		foreach ( array_slice( $ids, 0, 3 ) as $old ) {
			$this->assertNull( get_post( $old ), 'older backups are deleted permanently' );
		}
		$this->assertNotNull( Backups::get( $keep_other_kind ) );
		$this->assertNotNull( Backups::get( $keep_other_source ) );

		Backups::prune( 5, 'post_content', 2 );
		$this->assertSame( array( $ids[22], $ids[21] ), wp_list_pluck( Backups::list( 5, 'post_content', 100 ), 'id' ) );
	}

	public function test_backup_cpt_not_public() {
		$type = get_post_type_object( Backups::POST_TYPE );
		$this->assertInstanceOf( WP_Post_Type::class, $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertFalse( $type->show_ui );
		$this->assertFalse( $type->show_in_menu );
		$this->assertFalse( $type->show_in_nav_menus );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( $type->exclude_from_search );
		$this->assertFalse( $type->rewrite );
		$this->assertFalse( $type->query_var );
		$this->assertFalse( $type->can_export );
		$this->assertFalse( $type->map_meta_cap );
		foreach ( (array) $type->cap as $cap ) {
			$this->assertSame( 'manage_options', $cap );
		}

		$this->assertArrayNotHasKey( Backups::POST_TYPE, PostSupport::post_types(), 'never exposed to the content tools' );
		$this->assertNotContains( Backups::POST_TYPE, get_post_types( array( 'public' => true ) ) );
		$this->assertArrayNotHasKey( '/wp/v2/' . Backups::POST_TYPE, rest_get_server()->get_routes() );

		$id = Backups::save( 'post_content', 1, 'secret', array(), '' );
		$this->assertTrue( user_can( $this->admin, 'edit_post', $id ) );
		$this->assertFalse( user_can( $this->editor, 'edit_post', $id ) );
		$this->assertFalse( user_can( $this->editor, 'read_post', $id ) );
		$this->assertFalse( user_can( $this->editor, 'delete_post', $id ) );

		// Private status + read_private_posts = manage_options: nobody else can load it on the front end.
		foreach ( array( $this->editor, 0 ) as $user ) {
			wp_set_current_user( $user );
			$this->go_to( add_query_arg( 'p', $id, home_url( '/' ) ) );
			$this->assertFalse( is_singular( Backups::POST_TYPE ), 'user ' . $user );
			$this->assertSame( array(), $GLOBALS['wp_query']->posts, 'user ' . $user );
		}
		wp_set_current_user( $this->admin );
		$search = new WP_Query( array( 's' => 'secret', 'post_type' => 'any' ) );
		$this->assertSame( 0, $search->found_posts );
	}

	public function test_env_without_divi() {
		$this->assertFalse( Env::divi_active() );
		$this->assertNull( Env::divi_version() );
	}
}
