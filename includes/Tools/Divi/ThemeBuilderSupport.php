<?php
/**
 * Shared helpers for the Theme Builder tools.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Backups;
use WPDM\Divi\DiviRest;
use WPDM\Mcp\Context;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Not a tool. Every Theme Builder call goes through here and always passes
 * live=true: Divi's draft mode (live=false) starts an empty draft Theme
 * Builder that, saved from the Theme Builder screen, would replace the
 * published one. Routes and params: Divi 5.19.1
 * VisualBuilder/REST/RESTRegistration.php:1305-1358 and
 * VisualBuilder/REST/OutsideVb/OutsideVbController.php (list 246, create 277,
 * delete 471, upsert 784; args 1304-1552).
 */
final class ThemeBuilderSupport {

	/** Slots upsert-layout accepts (OutsideVbController.php:794, 1533-1540). */
	const LAYOUT_TYPES = array( 'header', 'footer' );

	/** Every slot a template has (theme-builder.php et_theme_builder_get_template). */
	const SLOTS = array( 'header', 'body', 'footer' );

	/**
	 * Call a Theme Builder route on the live (published) Theme Builder.
	 *
	 * @param string $action list-templates|create-template|delete-template|upsert-layout.
	 * @param array  $params Params; live is always forced to true.
	 * @return array
	 * @throws ToolError From DiviRest.
	 */
	public static function call( string $action, array $params = array() ): array {
		$params['live'] = true;
		return DiviRest::call( '/outside-vb/theme-builder/' . $action, $params );
	}

	/**
	 * Templates of the live Theme Builder.
	 *
	 * @return array{theme_builder_id: int, templates: array[]}
	 */
	public static function templates(): array {
		$data = self::call( 'list-templates' );
		return array(
			'theme_builder_id' => (int) ( $data['themeBuilderId'] ?? 0 ),
			'templates'        => array_values( is_array( $data['templates'] ?? null ) ? $data['templates'] : array() ),
		);
	}

	/**
	 * A template of the live Theme Builder, or null.
	 *
	 * @param int $template_id Template id.
	 * @return array|null
	 */
	public static function find( int $template_id ): ?array {
		if ( $template_id <= 0 ) {
			return null;
		}
		foreach ( self::templates()['templates'] as $template ) {
			if ( is_array( $template ) && (int) ( $template['id'] ?? 0 ) === $template_id ) {
				return $template;
			}
		}
		return null;
	}

	/**
	 * @param int $template_id Template id.
	 * @return array
	 * @throws ToolError not_found.
	 */
	public static function require_template( int $template_id ): array {
		$template = self::find( $template_id );
		if ( null === $template ) {
			throw new ToolError( 'Theme Builder template ' . $template_id . ' not found in the published Theme Builder (see divi_list_theme_builder_templates).', 'not_found' );
		}
		return $template;
	}

	/**
	 * Whether a template is the Default Website Template. Fails closed: a
	 * template without a "default" key is treated as the default too.
	 *
	 * Fields from Divi 5.19.1 et_theme_builder_get_template()
	 * (includes/builder/frontend-builder/theme-builder/theme-builder.php:495
	 * 'default' <= post meta _et_default === '1').
	 *
	 * @param array $template Template.
	 * @return bool
	 */
	public static function is_default( array $template ): bool {
		if ( ! array_key_exists( 'default', $template ) || ! empty( $template['default'] ) ) {
			return true;
		}
		$id = (int) ( $template['id'] ?? 0 );
		return $id > 0 && '1' === (string) get_post_meta( $id, '_et_default', true );
	}

	/**
	 * Where an enabled template is assigned (its use_on conditions); empty
	 * when it is disabled or unassigned, so nothing on the site uses it.
	 *
	 * Fields from theme-builder.php:496 'enabled' (post meta _et_enabled ===
	 * '1') and :518 'use_on' (post meta _et_use_on, one row per condition).
	 * A missing "enabled" key counts as enabled (fail closed).
	 *
	 * @param array $template Template.
	 * @return string[]
	 */
	public static function live_on( array $template ): array {
		$enabled = ! array_key_exists( 'enabled', $template ) || ! empty( $template['enabled'] );
		if ( ! $enabled ) {
			return array();
		}
		$use_on = $template['use_on'] ?? null;
		if ( ! is_array( $use_on ) ) {
			$id     = (int) ( $template['id'] ?? 0 );
			$use_on = $id > 0 ? get_post_meta( $id, '_et_use_on', false ) : array();
		}
		return array_values( array_filter( array_map( 'strval', is_array( $use_on ) ? $use_on : array() ), 'strlen' ) );
	}

	/**
	 * Refuse a write to a live (enabled and assigned) non-default template
	 * without confirmation. The Default Website Template is covered by
	 * check_default() instead.
	 *
	 * @param array $template Template.
	 * @param bool  $confirm  confirm_live_template.
	 * @return void
	 * @throws ToolError confirmation_required.
	 */
	public static function check_live( array $template, bool $confirm ): void {
		if ( self::is_default( $template ) || $confirm ) {
			return;
		}
		$live_on = self::live_on( $template );
		if ( array() === $live_on ) {
			return;
		}
		throw new ToolError(
			'Template ' . (int) $template['id'] . ' is live on: ' . implode( ', ', $live_on ) . '. The change shows on those pages at once. Ask the user, then pass confirm_live_template=true; or build on a new unassigned template (divi_create_theme_builder_template).',
			'confirmation_required',
			array(
				'template_id' => (int) $template['id'],
				'use_on'      => $live_on,
			)
		);
	}

	/**
	 * Both guards: the Default Website Template needs confirm_default_template,
	 * any other live template needs confirm_live_template.
	 *
	 * @param array $template Template.
	 * @param array $args     Validated tool args.
	 * @return void
	 * @throws ToolError confirmation_required.
	 */
	public static function check_writable( array $template, array $args ): void {
		self::check_default( $template, (bool) ( $args['confirm_default_template'] ?? false ) );
		self::check_live( $template, (bool) ( $args['confirm_live_template'] ?? false ) );
	}

	/**
	 * @return array
	 */
	public static function confirm_live_property(): array {
		return array(
			'type'        => 'boolean',
			'description' => 'Required (true) when the template is enabled and assigned (use_on not empty): the change shows on those pages at once. Not needed for the Default Website Template (confirm_default_template covers it).',
		);
	}

	/**
	 * Refuse a write to the Default Website Template without confirmation.
	 *
	 * @param array $template Template.
	 * @param bool  $confirm  confirm_default_template.
	 * @return void
	 * @throws ToolError confirmation_required.
	 */
	public static function check_default( array $template, bool $confirm ): void {
		if ( self::is_default( $template ) && ! $confirm ) {
			throw new ToolError(
				'Template ' . (int) $template['id'] . ' is the Default Website Template: changing it changes the header/footer of the whole site at once. Ask the user, then pass confirm_default_template=true; or build on a new template (divi_create_theme_builder_template) and assign it in Divi > Theme Builder.',
				'confirmation_required',
				array( 'template_id' => (int) $template['id'] )
			);
		}
	}

	/**
	 * @return array
	 */
	public static function confirm_property(): array {
		return array(
			'type'        => 'boolean',
			'description' => 'Required (true) when the template is the Default Website Template: the change applies to the whole site at once.',
		);
	}

	/**
	 * Layout post id in a template slot (0 when none).
	 *
	 * @param array  $template    Template.
	 * @param string $layout_type Slot.
	 * @return int
	 */
	public static function layout_id( array $template, string $layout_type ): int {
		return (int) ( $template['layouts'][ $layout_type ]['id'] ?? 0 );
	}

	/**
	 * Stored content of a layout post, read from the database now; null when missing.
	 *
	 * @param int $layout_id Layout post id.
	 * @return string|null
	 */
	public static function layout_content( int $layout_id ): ?string {
		if ( $layout_id <= 0 ) {
			return null;
		}
		clean_post_cache( $layout_id );
		$post = get_post( $layout_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		return (string) get_post_field( 'post_content', $post->ID, 'raw' );
	}

	/**
	 * Back up what a template slot holds now. An empty or missing slot is
	 * recorded too (empty content, meta created=true).
	 *
	 * @param array   $template    Template.
	 * @param string  $layout_type Slot.
	 * @param Context $ctx         Context.
	 * @param array   $extra       Extra meta.
	 * @return array{backup_id: int, layout_id: int}
	 * @throws ToolError backup_failed.
	 */
	public static function backup_slot( array $template, string $layout_type, Context $ctx, array $extra = array() ): array {
		$layout_id = self::layout_id( $template, $layout_type );
		$content   = self::layout_content( $layout_id );
		$meta      = array(
			'layout_type'    => $layout_type,
			'layout_id'      => null === $content ? 0 : $layout_id,
			'template_title' => (string) ( $template['title'] ?? '' ),
			'default'        => self::is_default( $template ),
		);
		if ( null === $content ) {
			$meta['created'] = true;
		}
		$id = Backups::save(
			'theme_builder',
			(int) $template['id'],
			(string) $content,
			array_merge( $meta, $extra ),
			(string) ( $ctx->connection['label'] ?? '' )
		);
		return array(
			'backup_id' => $id,
			'layout_id' => $meta['layout_id'],
		);
	}

	/**
	 * Create or replace a header/footer layout of a template.
	 *
	 * @param int    $template_id Template id.
	 * @param string $layout_type header|footer.
	 * @param string $content     Block markup.
	 * @return array{layout_id: int, action: string}
	 * @throws ToolError From DiviRest.
	 */
	public static function upsert( int $template_id, string $layout_type, string $content ): array {
		$data = self::call(
			'upsert-layout',
			array(
				'template_id'    => $template_id,
				'layout_type'    => $layout_type,
				'layout_content' => $content,
			)
		);
		return array(
			'layout_id' => (int) ( $data['layoutId'] ?? 0 ),
			'action'    => (string) ( $data['action'] ?? '' ),
		);
	}

	private function __construct() {}
}
