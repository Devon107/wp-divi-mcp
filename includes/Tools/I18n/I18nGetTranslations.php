<?php
/**
 * Tool: i18n_get_translations.
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
 * Lists the translations of a post.
 */
final class I18nGetTranslations extends Tool {

	public function name(): string {
		return 'i18n_get_translations';
	}

	public function description(): string {
		return 'Get the language of a post or page and its translations: language (null when none is set) and translations, keyed by language code (see i18n_status), each with id, title, status and link. The group includes the post itself. Translations you may not read (drafts of other authors) are left out.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID, in any language of the group.',
				),
			),
			'required'             => array( 'id' ),
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
		$post      = PostSupport::find_post( $args['id'] );
		if ( ! I18nSupport::can_read( $post ) ) {
			throw new ToolError( 'You are not allowed to read post ' . $post->ID . '.', 'forbidden' );
		}

		return array(
			'id'           => (int) $post->ID,
			'language'     => $languages->post_language( $post->ID ),
			'translations' => I18nSupport::group( $languages, $post->ID ),
		);
	}
}
