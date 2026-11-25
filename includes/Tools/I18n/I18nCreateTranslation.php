<?php
/**
 * Tool: i18n_create_translation.
 *
 * @package WPDM
 */

namespace WPDM\Tools\I18n;

use WPDM\I18n\Translator;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the translation of a post with the same design, as a linked draft.
 */
final class I18nCreateTranslation extends Tool {

	public function name(): string {
		return 'i18n_create_translation';
	}

	public function description(): string {
		return 'Step 2 of a translation: send the translated segments from i18n_extract (same source_hash). Copies the source exactly with the texts swapped in, as a draft in target_language (codes from i18n_status) linked to the source. Creates missing global module versions (listed in created_global_modules), points internal links to their translations, translates the Yoast fields. Missing segments keep the source text; a segment that lost a ⟦n⟧ marker or an HTML tag keeps the source text; both are reported in warnings. conflict when the source changed (extract again) or a translation already exists (existing_id). Review the draft, publish later.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'source_id'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID of the source, as passed to i18n_extract.',
				),
				'target_language' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Language code of the translation (see i18n_status).',
				),
				'source_hash'     => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'source_hash from i18n_extract.',
				),
				'segments'        => array(
					'type'                 => 'object',
					'description'          => 'Translated text by segment id: {"t": "Accueil", "b4.content": "<p>Bonjour</p>"}.',
					'additionalProperties' => array( 'type' => 'string' ),
				),
				'slug'            => array(
					'type'        => 'string',
					'description' => 'URL slug of the translation (optional).',
				),
			),
			'required'             => array( 'source_id', 'target_language', 'source_hash', 'segments' ),
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
		$target    = I18nSupport::code( $languages, $args['target_language'] );
		$post      = PostSupport::find_post( $args['source_id'] );
		I18nSupport::require_edit( $post );
		I18nSupport::translatable( $languages, $post );
		if ( 'trash' === $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is in the trash. Restore it with restore_post first.', 'invalid_state' );
		}
		return Translator::create( $post, $languages, $target, $args['source_hash'], $args['segments'], $args['slug'] ?? null );
	}
}
