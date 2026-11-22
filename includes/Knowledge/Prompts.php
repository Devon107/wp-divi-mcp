<?php
/**
 * MCP prompts of the knowledge layer: build_divi_page and match_existing_site.
 *
 * Each renders one user message that walks Claude through the workflow of
 * divi://guide (read it first, reuse the site's globals, study a similar page,
 * build a tree, validate, create a draft, dry run, apply, share the links).
 *
 * @package WPDM
 */

namespace WPDM\Knowledge;

use WPDM\Mcp\Prompt;
use WPDM\Mcp\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the prompts.
 */
final class Prompts {

	/**
	 * Longest argument value kept (characters); the rest is cut.
	 */
	const MAX_ARG = 2000;

	/**
	 * Add the prompts to a registry.
	 *
	 * @param Registry $registry Registry.
	 * @return void
	 */
	public static function register( Registry $registry ): void {
		$registry->add_prompt(
			new Prompt(
				'build_divi_page',
				'Build a new Divi 5 page on this site, in a draft, following divi://guide.',
				array(
					array(
						'name'        => 'purpose',
						'description' => 'What the page is for, e.g. "landing page for a dental clinic".',
						'required'    => true,
					),
					array(
						'name'        => 'sections',
						'description' => 'Sections wanted, e.g. "hero, services, testimonials, contact".',
						'required'    => false,
					),
					array(
						'name'        => 'language',
						'description' => 'Language of the copy, e.g. "Spanish".',
						'required'    => false,
					),
					array(
						'name'        => 'brand',
						'description' => 'Brand colors, fonts or tone to use (default: the site\'s own).',
						'required'    => false,
					),
				),
				array( self::class, 'build_divi_page' )
			)
		);
		$registry->add_prompt(
			new Prompt(
				'match_existing_site',
				'Study an existing Divi page and build new designs that match its look.',
				array(
					array(
						'name'        => 'post_id',
						'description' => 'ID of the page or post whose design to match.',
						'required'    => true,
					),
				),
				array( self::class, 'match_existing_site' )
			)
		);
	}

	/**
	 * Render build_divi_page.
	 *
	 * @param array $args purpose, sections?, language?, brand?.
	 * @return array[] One user message.
	 */
	public static function build_divi_page( array $args ): array {
		$purpose  = self::arg( $args, 'purpose' );
		$sections = self::arg( $args, 'sections' );
		$language = self::arg( $args, 'language' );
		$brand    = self::arg( $args, 'brand' );

		$text = "Build a new Divi 5 page on this WordPress site.\n\n"
			. 'Purpose: ' . $purpose . "\n"
			. 'Sections: ' . ( '' !== $sections ? $sections : 'choose the sections that fit the purpose (for example hero, benefits or services cards, social proof, CTA)' ) . "\n"
			. 'Language of the copy: ' . ( '' !== $language ? $language : 'the language of this conversation (or of the site, if that is clear)' ) . "\n"
			. 'Brand: ' . ( '' !== $brand ? $brand : 'reuse the site\'s own global colors, variables and fonts' ) . "\n\n"
			. "Steps:\n"
			. "1. Read the resource divi://guide first and follow it. Use divi://attributes and divi://modules/{name} when you need an attribute shape.\n"
			. "2. Call divi_status (Divi version, what you may do) and divi_get_globals. Reuse the site's real global colors and variables, and check which built-in color the site uses as its accent.\n"
			. "3. Find a similar existing page (list_posts with post_type page) and study it with divi_inspect_page (mode tree, then mode section for a representative section) so the new page matches the site's look. If exports or pages disagree, follow the most recent one.\n"
			. "4. Write real, specific copy (no lorem ipsum). Build a layout tree by composition: section > row > column > group > text / icon / image / link and the patterns (section_header, icon_card, pill_link...), not blurb, testimonial, team_member, button or cta modules. Find icons with divi_find_icon.\n"
			. "5. Run divi_validate_layout. Fix every error and every contrast warning, then validate again.\n"
			. "6. Create a new draft page with divi_create_page (title only), then call divi_apply_layout on it with dry_run=true, check the preview, and apply it without dry_run.\n"
			. "7. Share the view and edit_divi links from the result, and list what the user should replace (images, links, phone numbers).\n\n"
			. 'Never change a published page or the Theme Builder for this; it is a new draft.';

		return self::message( $text );
	}

	/**
	 * Render match_existing_site.
	 *
	 * @param array $args post_id.
	 * @return array[] One user message.
	 * @throws \InvalidArgumentException When post_id is not a positive integer.
	 */
	public static function match_existing_site( array $args ): array {
		$raw = $args['post_id'] ?? '';
		$id  = is_int( $raw ) ? $raw : ( is_string( $raw ) && preg_match( '/^\s*\d+\s*$/', $raw ) ? (int) trim( $raw ) : 0 );
		if ( $id <= 0 ) {
			throw new \InvalidArgumentException( 'post_id must be a positive integer.' );
		}

		$text = "Study the Divi design of post {$id} on this site and build new designs that match it.\n\n"
			. "Steps:\n"
			. "1. Read the resource divi://guide first and follow it.\n"
			. "2. Call divi_status and divi_get_globals.\n"
			. "3. Call divi_inspect_page with post_id {$id}: mode tree for the outline (skip sections marked [HIDDEN]), mode section for two or three representative sections (hero, a card section, a CTA), mode globals for the colors and variables it uses, and mode paths with block divi/link (or divi/button) for the button style.\n"
			. "4. Summarize the design tokens you found: row width and max width, section padding (desktop/tablet/phone), card background, border, radius, padding and hover, heading fonts, sizes and weights, body text, the pill/link style and which global color is the accent. Express them as a layout tree `theme` plus reusable attrs, referencing the site's global ids with {\"\$color_var\": ...} / {\"\$content_var\": ...}.\n"
			. "5. When the user asks for a page or section, build the layout tree with those tokens (composition, not blurb/testimonial/button/cta modules), run divi_validate_layout and fix every error, then create it as a new draft with divi_create_page and preview it with divi_apply_layout dry_run=true before applying.\n\n"
			. "Do not modify post {$id}. Change an existing page only when the user explicitly asks, and say which page you used as the reference.";

		return self::message( $text );
	}

	/**
	 * A prompt argument as a trimmed, length-capped string ('' when absent).
	 *
	 * @param array  $args Arguments.
	 * @param string $key  Key.
	 * @return string
	 */
	private static function arg( array $args, string $key ): string {
		$value = $args[ $key ] ?? '';
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		if ( strlen( $value ) > self::MAX_ARG ) {
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_ARG ) : substr( $value, 0, self::MAX_ARG );
		}
		return $value;
	}

	/**
	 * @param string $text Text.
	 * @return array[]
	 */
	private static function message( string $text ): array {
		return array(
			array(
				'role'    => 'user',
				'content' => array(
					'type' => 'text',
					'text' => $text,
				),
			),
		);
	}

	private function __construct() {}
}
