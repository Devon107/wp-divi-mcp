<?php
/**
 * Tool: i18n_extract.
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
 * Lists the translatable texts of a post, step one of a same-design translation.
 */
final class I18nExtract extends Tool {

	public function name(): string {
		return 'i18n_extract';
	}

	public function description(): string {
		return 'Step 1 of translating a post or Divi page into another language (codes from i18n_status). Returns source_hash and the numbered segments to translate: {id, kind, context, text}. t = title, x = excerpt, seo.* = Yoast fields, b<n>... = Divi module texts (b<n>.ld<k>.<path> = a text in a JSON-LD script; its inLanguage and the site URLs are set automatically), g<id>... = texts of a global module with no target version yet. kind html: keep the same HTML tags and every ⟦n⟧ marker exactly once (protected code). Also global_modules (translation null = it will be created) and warnings. Then send ALL translations back in ONE i18n_create_translation call. Very large pages (roughly over 250 segments) may exceed the client\'s tool-output limit: translate smaller pages (paging is planned). Changes nothing.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'              => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID of the source (it needs a language).',
				),
				'target_language' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Language code to translate into (see i18n_status).',
				),
			),
			'required'             => array( 'id', 'target_language' ),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args      = PostSupport::validate( $this->input_schema(), $args );
		$languages = I18nSupport::adapter();
		$target    = I18nSupport::code( $languages, $args['target_language'] );
		$post      = PostSupport::find_post( $args['id'] );
		if ( ! I18nSupport::can_read( $post ) ) {
			throw new ToolError( 'You are not allowed to read post ' . $post->ID . '.', 'forbidden' );
		}
		I18nSupport::translatable( $languages, $post );
		return Translator::extract( $post, $languages, $target );
	}
}
