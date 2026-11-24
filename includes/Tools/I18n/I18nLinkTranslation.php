<?php
/**
 * Tool: i18n_link_translation.
 *
 * @package WPDM
 */

namespace WPDM\Tools\I18n;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Links a post as the translation of another post.
 */
final class I18nLinkTranslation extends Tool {

	public function name(): string {
		return 'i18n_link_translation';
	}

	public function description(): string {
		return "Link two existing posts as translations of each other (nothing is copied). Both need a language already (i18n_set_language; codes from i18n_status), in different languages, and the same post type. Fails with conflict when the source already has a different post in that language, or the translation is already linked to another post. Returns the translation group by language code.";
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'source_id'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID of the original (any language of its group).',
				),
				'translation_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID to add as a translation. Its language is the one it already has.',
				),
			),
			'required'             => array( 'source_id', 'translation_id' ),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args      = PostSupport::validate( $this->input_schema(), $args );
		$languages = I18nSupport::adapter();
		if ( $args['source_id'] === $args['translation_id'] ) {
			throw new ToolError( 'A post cannot be its own translation: pass two different posts.', 'invalid_argument' );
		}
		$source      = PostSupport::find_post( $args['source_id'] );
		$translation = PostSupport::find_post( $args['translation_id'] );
		I18nSupport::require_edit( $source );
		I18nSupport::require_edit( $translation );
		I18nSupport::translatable( $languages, $source );
		if ( $source->post_type !== $translation->post_type ) {
			throw new ToolError( sprintf( 'Post types differ: post %d is a %s, post %d is a %s.', $source->ID, $source->post_type, $translation->ID, $translation->post_type ), 'invalid_argument' );
		}

		$source_language = $languages->post_language( $source->ID );
		if ( null === $source_language ) {
			throw new ToolError( 'Post ' . $source->ID . ' has no language: set it with i18n_set_language first.', 'invalid_argument' );
		}
		$code = $languages->post_language( $translation->ID );
		if ( null === $code ) {
			throw new ToolError( 'Post ' . $translation->ID . ' has no language: set it with i18n_set_language first.', 'invalid_argument' );
		}
		if ( $code === $source_language ) {
			throw new ToolError( sprintf( 'Both posts are in %s: a translation needs a different language.', $code ), 'invalid_argument' );
		}

		$source_group = $languages->translations( $source->ID );
		$existing     = $source_group[ $code ] ?? null;
		if ( null !== $existing && $existing !== $translation->ID ) {
			throw new ToolError( sprintf( 'Post %d already is the %s translation of post %d.', $existing, $code, $source->ID ), 'conflict' );
		}
		$taken_from = array_diff( $languages->translations( $translation->ID ), array( $translation->ID ) );
		if ( array() !== $taken_from && ! in_array( $source->ID, $taken_from, true ) ) {
			throw new ToolError( sprintf( 'Post %d is already linked to other translations (post %s); linking it here would break that group.', $translation->ID, implode( ', ', $taken_from ) ), 'conflict' );
		}

		try {
			$languages->link( $source->ID, $translation->ID, $code );
		} catch ( \InvalidArgumentException $e ) {
			throw new ToolError( $e->getMessage(), 'invalid_argument' );
		}

		return array( 'translations' => I18nSupport::group( $languages, $source->ID ) );
	}
}
