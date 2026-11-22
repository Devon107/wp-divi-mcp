<?php
/**
 * Server instructions sent on initialize (hooked on wpdm_instructions).
 *
 * Kept under 1500 characters: the essentials only, with a pointer to divi://guide.
 *
 * @package WPDM
 */

namespace WPDM\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * The MCP server instructions.
 */
final class Instructions {

	const MAX = 1500;

	/**
	 * The instructions text.
	 *
	 * @return string
	 */
	public static function text(): string {
		return implode(
			"\n",
			array(
				'WP Divi MCP manages this WordPress site (posts, pages, media, menus, terms, options) and builds Divi 5 pages from a JSON layout tree.',
				'Read the resource divi://guide before building Divi pages (also divi://attributes, divi://modules/{name}, divi://schema/layout).',
				'Workflow: divi_status -> divi_get_globals -> divi_export_page / divi_inspect_page on a similar page -> build a layout tree -> divi_validate_layout -> divi_create_page (draft) -> divi_apply_layout with dry_run=true -> apply -> share the view / edit_divi links.',
				'Build by composition: section > row > column > group > text / icon / image / link and the patterns (section_header, icon_card, pill_link...), not the blurb, testimonial, team_member, button or cta modules. Reuse the site\'s global colors and variables.',
				'New designs go into a new draft page; change an existing page only when the user asks.',
				'Confirm flags: confirm_published=true is required to change a published, scheduled or private page; confirm_default_template=true to change the Default Website Template (the whole site\'s header/footer). Ask the user before setting either.',
				'Divi changes are backed up first (divi_list_backups, divi_restore_backup). Posts and pages are only trashed (trash_post, restore_post). Exception: update_menu_items permanently deletes the menu items it removes, so confirm with the user first; divi_delete_theme_builder_template moves a template to the Divi trash.',
			)
		);
	}

	/**
	 * wpdm_instructions filter: these instructions, after any text already there.
	 *
	 * @param mixed $existing Instructions so far.
	 * @return string
	 */
	public static function filter( $existing ): string {
		$existing = is_string( $existing ) ? trim( $existing ) : '';
		return '' === $existing ? self::text() : $existing . "\n\n" . self::text();
	}

	private function __construct() {}
}
