<?php
/**
 * Tool: edit_post_content.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Divi\Backups;
use WPDM\Divi\BlockScanner;
use WPDM\I18n\Segments;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces exact snippets in a post's content, so small fixes do not resend the whole page.
 */
final class EditPostContent extends Tool {

	/** Most edits one call may apply. */
	const MAX_EDITS = 50;

	/** Characters of context returned around each replacement. */
	const CONTEXT = 60;

	public function name(): string {
		return 'edit_post_content';
	}

	public function description(): string {
		return 'Replace exact text in the content of a post or page, Divi pages included, without sending the whole content: much cheaper than update_post for small fixes (a typo, a URL, a JSON-LD value). Each edit has old_string (exact raw text as get_post content_raw shows it, block markup included) and new_string; old_string must appear exactly once unless replace_all=true. Edits apply in order and all or nothing. The Divi block structure cannot change (use the Divi layout tools for that). The current content is backed up first (backup_id). Published, scheduled or private posts need confirm_published=true.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'                => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the post to edit.',
				),
				'edits'             => array(
					'type'        => 'array',
					'description' => 'Replacements, applied in order: [{"old_string": "...", "new_string": "...", "replace_all": false}]. At most ' . self::MAX_EDITS . '.',
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'old_string'  => array( 'type' => 'string' ),
							'new_string'  => array( 'type' => 'string' ),
							'replace_all' => array( 'type' => 'boolean' ),
						),
						'required'             => array( 'old_string', 'new_string' ),
						'additionalProperties' => false,
					),
				),
				'confirm_published' => array(
					'type'        => 'boolean',
					'description' => 'Required (true) to edit a published, scheduled or private post: the change is visible at once.',
				),
			),
			'required'             => array( 'id', 'edits' ),
			'additionalProperties' => false,
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = PostSupport::find_post( $args['id'] );

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post->ID . '.', 'forbidden' );
		}
		if ( 'trash' === $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is in the trash. Restore it with restore_post first.', 'invalid_state' );
		}
		if ( empty( $args['confirm_published'] ) && in_array( $post->post_status, UpdatePost::PUBLISHED, true ) ) {
			throw new ToolError(
				'Post ' . $post->ID . ' is ' . $post->post_status . '; editing its content would be visible at once. Ask the user, then pass confirm_published=true (the current content is backed up first).',
				'confirmation_required',
				array(
					'post_id' => $post->ID,
					'status'  => $post->post_status,
				)
			);
		}

		clean_post_cache( $post->ID );
		$before = (string) get_post_field( 'post_content', $post->ID, 'raw' );
		list( $after, $changes ) = self::apply_edits( $before, self::edits( $args['edits'] ) );

		if ( $after === $before ) {
			return array_merge(
				PostFormatter::summary( $post ),
				array(
					'content_length' => strlen( $before ),
					'replacements'   => 0,
					'changes'        => $changes,
				)
			);
		}
		if ( self::structure( $before ) !== self::structure( $after ) ) {
			throw new ToolError( 'These edits change the block structure (a block comment was added, removed or renamed). Only text and attribute values can change here; use the Divi layout tools to change the structure.', 'structure_changed' );
		}
		if ( '' !== $after && '' === wp_check_invalid_utf8( $after ) ) {
			throw new ToolError( 'The edited content is not valid UTF-8.', 'invalid_argument' );
		}

		$backup_id = Backups::save(
			'post_content',
			$post->ID,
			$before,
			array(
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'reason'    => 'edit_post_content',
			),
			(string) ( $ctx->connection['label'] ?? '' )
		);

		self::write( $post->ID, $after );

		$updated = get_post( $post->ID );
		return array_merge(
			PostFormatter::summary( $updated ),
			array(
				'content_length' => strlen( (string) $updated->post_content ),
				'replacements'   => array_sum( array_column( $changes, 'count' ) ),
				'changes'        => $changes,
				'backup_id'      => $backup_id,
			)
		);
	}

	/**
	 * Validate the edits list.
	 *
	 * @param array $edits Raw edits.
	 * @return array
	 * @throws ToolError invalid_argument.
	 */
	private static function edits( array $edits ): array {
		if ( ! $edits ) {
			throw new ToolError( 'Pass at least one edit.', 'invalid_argument' );
		}
		if ( count( $edits ) > self::MAX_EDITS ) {
			throw new ToolError( 'At most ' . self::MAX_EDITS . ' edits per call.', 'invalid_argument' );
		}
		$out = array();
		foreach ( $edits as $i => $edit ) {
			if ( ! is_array( $edit ) || ! isset( $edit['old_string'], $edit['new_string'] ) || ! is_string( $edit['old_string'] ) || ! is_string( $edit['new_string'] ) ) {
				throw new ToolError( 'Edit ' . $i . ' needs string old_string and new_string.', 'invalid_argument' );
			}
			$unknown = array_diff( array_keys( $edit ), array( 'old_string', 'new_string', 'replace_all' ) );
			if ( $unknown ) {
				throw new ToolError( 'Edit ' . $i . ' has unknown key(s): ' . implode( ', ', $unknown ) . '.', 'invalid_argument' );
			}
			if ( isset( $edit['replace_all'] ) && ! is_bool( $edit['replace_all'] ) ) {
				throw new ToolError( 'Edit ' . $i . ': replace_all must be a boolean.', 'invalid_argument' );
			}
			if ( '' === $edit['old_string'] ) {
				throw new ToolError( 'Edit ' . $i . ': old_string must not be empty.', 'invalid_argument' );
			}
			$out[] = array(
				'old'         => $edit['old_string'],
				'new'         => $edit['new_string'],
				'replace_all' => ! empty( $edit['replace_all'] ),
			);
		}
		return $out;
	}

	/**
	 * Apply edits in order. Pure.
	 *
	 * @param string $content Content.
	 * @param array  $edits   From edits().
	 * @return array{0: string, 1: array} New content and, per edit, {count, context}.
	 * @throws ToolError not_found or ambiguous.
	 */
	public static function apply_edits( string $content, array $edits ): array {
		$changes = array();
		foreach ( $edits as $i => $edit ) {
			$count = substr_count( $content, $edit['old'] );
			if ( 0 === $count ) {
				throw new ToolError( 'Edit ' . $i . ': old_string was not found in the content (after the earlier edits). Copy it exactly from get_post content_raw, escapes included.', 'not_found' );
			}
			if ( $count > 1 && ! $edit['replace_all'] ) {
				throw new ToolError( 'Edit ' . $i . ': old_string appears ' . $count . ' times. Add surrounding text to make it unique, or pass replace_all=true.', 'ambiguous', array( 'edit' => $i, 'count' => $count ) );
			}
			$at      = strpos( $content, $edit['old'] );
			$content = $edit['replace_all']
				? str_replace( $edit['old'], $edit['new'], $content )
				: substr_replace( $content, $edit['new'], $at, strlen( $edit['old'] ) );
			$start   = max( 0, $at - self::CONTEXT );
			$snippet = substr( $content, $start, $at - $start + strlen( $edit['new'] ) + self::CONTEXT );
			$changes[] = array(
				'count'   => $count,
				'context' => wp_check_invalid_utf8( $snippet, true ),
			);
		}
		return array( $content, $changes );
	}

	/**
	 * Block comment sequence (names and openers/closers), to keep the structure intact.
	 *
	 * @param string $content Content.
	 * @return array
	 */
	private static function structure( string $content ): array {
		$out = array();
		foreach ( BlockScanner::scan( $content ) as $b ) {
			$out[] = ( $b['closer'] ? '/' : '' ) . $b['name'];
		}
		return $out;
	}

	/**
	 * Save the content. For users without unfiltered_html, core kses is replaced by the same
	 * per-string kses the translation tools use (core kses escapes whole Divi block comments
	 * whose attributes hold HTML), and content with scripts, styles or SVG is refused rather
	 * than silently stripped.
	 *
	 * @param int    $post_id Post id.
	 * @param string $content New content.
	 * @return void
	 * @throws ToolError forbidden or update_failed.
	 */
	private static function write( int $post_id, string $content ): void {
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			if ( Segments::has_unsafe_markup( $content, 'wp_kses_post' ) ) {
				throw new ToolError( 'This content contains scripts, styles or SVG that your account cannot save (unfiltered_html). Ask an administrator to make this edit.', 'forbidden' );
			}
			$content = Segments::sanitize( $content, 'wp_kses_post' );
		}
		if ( $kses ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		}
		try {
			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					)
				),
				true
			);
		} finally {
			if ( $kses ) {
				add_filter( 'content_save_pre', 'wp_filter_post_kses' );
			}
		}
		if ( is_wp_error( $result ) ) {
			throw new ToolError( 'Could not update the post: ' . $result->get_error_message(), 'update_failed' );
		}
	}
}
