<?php
/**
 * Tool: i18n_set_language.
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
 * Sets the language of a post.
 */
final class I18nSetLanguage extends Tool {

	public function name(): string {
		return 'i18n_set_language';
	}

	public function description(): string {
		return 'Set the language of a post or page (content is not touched). The language is a code from i18n_status. Fails with conflict when the post is already linked to translations in other languages: changing its language would break that link. Link two posts that already have their languages with i18n_link_translation.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID.',
				),
				'language' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => "Language code, as listed by i18n_status (for example 'en', 'fr', 'pt-br').",
				),
			),
			'required'             => array( 'id', 'language' ),
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
		$post      = PostSupport::find_post( $args['id'] );
		I18nSupport::require_edit( $post );
		I18nSupport::translatable( $languages, $post );
		$code = I18nSupport::code( $languages, $args['language'] );

		if ( $languages->post_language( $post->ID ) !== $code ) {
			$others = array_diff( $languages->translations( $post->ID ), array( $post->ID ) );
			if ( array() !== $others ) {
				throw new ToolError(
					sprintf( 'Post %d is linked to translations (post %s); changing its language would break the link.', $post->ID, implode( ', ', $others ) ),
					'conflict'
				);
			}
			try {
				$languages->set_language( $post->ID, $code );
			} catch ( \InvalidArgumentException $e ) {
				throw new ToolError( $e->getMessage(), 'invalid_argument' );
			}
		}

		return array(
			'id'       => (int) $post->ID,
			'language' => $languages->post_language( $post->ID ),
		);
	}
}
