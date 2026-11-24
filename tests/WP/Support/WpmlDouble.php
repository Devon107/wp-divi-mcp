<?php
/**
 * In-memory stand-in for WPML's documented public hooks.
 *
 * WPML is commercial and cannot be installed in tests or CI, so the WPML adapter is
 * tested against this double. It implements only the hooks the adapter uses:
 * filters wpml_active_languages, wpml_default_language, wpml_post_language_details,
 * wpml_element_trid, wpml_get_element_translations, wpml_object_id,
 * wpml_is_translated_post_type; action wpml_set_element_language_details.
 *
 * Languages: en (default) and fr. Post types page and post are translatable.
 *
 * @package WPDM
 */

namespace WPDM\Tests\Support;

/**
 * WPML hooks over an in-memory element table.
 */
final class WpmlDouble {

	const FILTERS = array(
		'wpml_active_languages',
		'wpml_default_language',
		'wpml_post_language_details',
		'wpml_element_trid',
		'wpml_get_element_translations',
		'wpml_object_id',
		'wpml_is_translated_post_type',
	);

	const ACTION = 'wpml_set_element_language_details';

	/**
	 * Element table: element id => {trid, element_type, language_code, source_language_code}.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private static $elements = array();

	/**
	 * Last translation group id handed out.
	 *
	 * @var int
	 */
	private static $last_trid = 0;

	/**
	 * Callbacks other plugins (Polylang's WPML compatibility) had on our hooks, put back by uninstall().
	 *
	 * @var array<string,mixed>
	 */
	private static $stashed = array();

	/**
	 * Whether the hooks are installed.
	 *
	 * @var bool
	 */
	private static $installed = false;

	/**
	 * Register the hooks and empty the element table. Defines ICL_SITEPRESS_VERSION if it is not yet defined.
	 *
	 * Callbacks already registered on these hooks are set aside until uninstall(), so the double
	 * answers alone (Polylang ships a WPML compatibility layer on some of the same hooks).
	 *
	 * @return void
	 */
	public static function install(): void {
		self::uninstall();
		global $wp_filter;
		foreach ( array_merge( self::FILTERS, array( self::ACTION ) ) as $hook ) {
			if ( isset( $wp_filter[ $hook ] ) ) {
				self::$stashed[ $hook ] = $wp_filter[ $hook ];
				unset( $wp_filter[ $hook ] );
			}
		}
		self::$elements  = array();
		self::$last_trid = 0;

		add_filter( 'wpml_active_languages', array( self::class, 'active_languages' ), 10, 2 );
		add_filter( 'wpml_default_language', array( self::class, 'default_language' ), 10, 1 );
		add_filter( 'wpml_post_language_details', array( self::class, 'post_language_details' ), 10, 2 );
		add_filter( 'wpml_element_trid', array( self::class, 'element_trid' ), 10, 3 );
		add_filter( 'wpml_get_element_translations', array( self::class, 'element_translations' ), 10, 5 );
		add_filter( 'wpml_object_id', array( self::class, 'object_id' ), 10, 4 );
		add_filter( 'wpml_is_translated_post_type', array( self::class, 'is_translated_post_type' ), 10, 2 );
		add_action( self::ACTION, array( self::class, 'set_element_language_details' ), 10, 1 );

		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			define( 'ICL_SITEPRESS_VERSION', '4.7.0-double' );
		}
		self::$installed = true;
	}

	/**
	 * Remove the hooks and put back what install() set aside. Afterwards Languages::current() sees no WPML.
	 *
	 * ICL_SITEPRESS_VERSION stays defined (constants cannot be undefined); detection also needs the hooks.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( ! self::$installed ) {
			return;
		}
		global $wp_filter;
		foreach ( array_merge( self::FILTERS, array( self::ACTION ) ) as $hook ) {
			unset( $wp_filter[ $hook ] );
		}
		foreach ( self::$stashed as $hook => $callbacks ) {
			$wp_filter[ $hook ] = $callbacks;
		}
		self::$stashed   = array();
		self::$elements  = array();
		self::$installed = false;
	}

	/**
	 * Languages as WPML returns them, keyed by code.
	 *
	 * @param mixed $unused Incoming value.
	 * @return array<string,array<string,mixed>>
	 */
	public static function active_languages( $unused = null ): array {
		return array(
			'en' => array(
				'code'           => 'en',
				'id'             => 1,
				'native_name'    => 'English',
				'translated_name' => 'English',
				'default_locale' => 'en_US',
				'active'         => 1,
				'language_code'  => 'en',
			),
			'fr' => array(
				'code'           => 'fr',
				'id'             => 2,
				'native_name'    => 'Français',
				'translated_name' => 'French',
				'default_locale' => 'fr_FR',
				'active'         => 0,
				'language_code'  => 'fr',
			),
		);
	}

	/**
	 * Default language code.
	 *
	 * @param mixed $unused Incoming value.
	 * @return string
	 */
	public static function default_language( $unused = null ): string {
		return 'en';
	}

	/**
	 * Language details of a post, as WPML returns them (array with language_code), or a WP_Error-like false.
	 *
	 * @param mixed $unused  Incoming value.
	 * @param int   $post_id Post id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function post_language_details( $unused, $post_id = 0 ) {
		$row = self::$elements[ (int) $post_id ] ?? null;
		if ( null === $row ) {
			return new \WP_Error( 'missing_language', 'No language set for this post.' );
		}
		return array(
			'language_code'  => $row['language_code'],
			'locale'         => 'en' === $row['language_code'] ? 'en_US' : 'fr_FR',
			'text_direction' => false,
			'display_name'   => $row['language_code'],
			'native_name'    => $row['language_code'],
			'different_language' => false,
		);
	}

	/**
	 * Translation group id of an element, or null.
	 *
	 * @param mixed  $unused       Incoming value.
	 * @param int    $element_id   Element id.
	 * @param string $element_type Element type, post_<type>.
	 * @return int|null
	 */
	public static function element_trid( $unused, $element_id = 0, $element_type = 'post_post' ) {
		$row = self::$elements[ (int) $element_id ] ?? null;
		return null === $row ? null : (int) $row['trid'];
	}

	/**
	 * Members of a translation group, keyed by language code, as WPML does (objects with element_id).
	 *
	 * Like WPML, outside the admin and without $all_statuses it leaves out posts that are not
	 * publish, private or pending, unless the current user wrote them (so drafts disappear).
	 *
	 * @param mixed  $unused       Incoming value.
	 * @param int    $trid         Group id.
	 * @param string $element_type Element type.
	 * @param bool   $skip_empty   Unused.
	 * @param bool   $all_statuses Include every post status.
	 * @return array<string,\stdClass>
	 */
	public static function element_translations( $unused, $trid = 0, $element_type = 'post_post', $skip_empty = false, $all_statuses = false ): array {
		$out = array();
		foreach ( self::$elements as $element_id => $row ) {
			if ( (int) $row['trid'] !== (int) $trid ) {
				continue;
			}
			if ( ! $all_statuses && ! is_admin() && ! self::visible_status( (int) $element_id ) ) {
				continue;
			}
			$out[ $row['language_code'] ] = (object) array(
				'translation_id'       => $element_id,
				'language_code'        => $row['language_code'],
				'element_id'           => (string) $element_id,
				'source_language_code' => $row['source_language_code'],
				'element_type'         => $row['element_type'],
				'original'             => null === $row['source_language_code'] ? '1' : '0',
				'trid'                 => (string) $trid,
			);
		}
		return $out;
	}

	/**
	 * Whether a post shows in a front-end translation list: public statuses, or written by the current user.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function visible_status( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		return in_array( $post->post_status, array( 'publish', 'private', 'pending' ), true )
			|| ( get_current_user_id() && (int) $post->post_author === get_current_user_id() );
	}

	/**
	 * Translation of an element in a language (null when missing and not asked to fall back).
	 *
	 * @param int         $element_id          Element id.
	 * @param string      $element_type        Post type or taxonomy name.
	 * @param bool        $return_original     Return the original id when no translation exists.
	 * @param string|null $language_code       Target language.
	 * @return int|null
	 */
	public static function object_id( $element_id, $element_type = 'post', $return_original = false, $language_code = null ) {
		$row = self::$elements[ (int) $element_id ] ?? null;
		if ( null !== $row ) {
			foreach ( self::$elements as $other_id => $other ) {
				if ( $other['trid'] === $row['trid'] && $other['language_code'] === $language_code ) {
					return (int) $other_id;
				}
			}
		}
		return $return_original ? $element_id : null;
	}

	/**
	 * Whether a post type is translated.
	 *
	 * @param mixed  $unused    Incoming value.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_translated_post_type( $unused, $post_type = '' ): bool {
		return in_array( $post_type, array( 'post', 'page' ), true );
	}

	/**
	 * Action wpml_set_element_language_details: put an element in a language and a translation group.
	 *
	 * @param array<string,mixed> $args element_id, element_type, trid (int|false), language_code, source_language_code.
	 * @return void
	 */
	public static function set_element_language_details( $args ): void {
		$trid = $args['trid'] ?? false;
		if ( ! $trid ) {
			$trid = ++self::$last_trid;
		} else {
			self::$last_trid = max( self::$last_trid, (int) $trid );
		}
		self::$elements[ (int) $args['element_id'] ] = array(
			'trid'                 => (int) $trid,
			'element_type'         => (string) $args['element_type'],
			'language_code'        => (string) $args['language_code'],
			'source_language_code' => $args['source_language_code'] ?? null,
		);
	}
}
