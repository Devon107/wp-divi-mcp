<?php
/**
 * Same-design translation of a post: extract its texts, then create the translated copy.
 *
 * extract() lists the translatable segments of a source post (title, excerpt, Divi / block
 * content, Yoast fields, and the content of global modules that have no target version yet).
 * create() copies the source exactly with the translated segments swapped in, creates the
 * missing global module versions, rewrites internal links to their translations and saves the
 * result as a draft in the target language, linked to the source. The source, its library items
 * and existing translations are only read.
 *
 * @package WPDM
 */

namespace WPDM\I18n;

use WPDM\Mcp\ToolError;
use WPDM\Seo\Yoast;
use WPDM\Tools\Divi\DiviSupport;
use WPDM\Tools\I18n\I18nSupport;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Translation orchestration over Segments and the language adapter.
 */
final class Translator {

	/**
	 * Post type of the Divi library (global modules).
	 */
	const LIBRARY_TYPE = 'et_pb_layout';

	/**
	 * Taxonomies Polylang keeps its own bookkeeping in.
	 */
	const LANGUAGE_TAXONOMIES = array( 'language', 'post_translations', 'term_language', 'term_translations' );

	/**
	 * Meta never copied to a translation: WordPress bookkeeping, this plugin's, the language
	 * plugins', and a canonical URL (it would point the translation at the source).
	 */
	const SKIPPED_META = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_thumbnail_id', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug', '_encloseme', '_pingme', '_yoast_wpseo_canonical' );

	/**
	 * Meta key prefixes never copied (this plugin, WPML, Polylang, Divi caches of the old content).
	 */
	const SKIPPED_META_PREFIXES = array( '_wpdm_', '_icl_', '_wpml_', '_pll_', '_et_dynamic_cached_', '_et_builder_module_features_cache' );

	/**
	 * Translatable segments of a source post, as i18n_extract returns them.
	 *
	 * @param \WP_Post  $src    Source post (readable, translatable type).
	 * @param Languages $l      Language adapter.
	 * @param string    $target Target language code (valid).
	 * @return array{source_id:int, source_language:string, target_language:string, source_hash:string, segments:array, global_modules:array, warnings:string[]}
	 * @throws ToolError invalid_argument (legacy content, no source language, same language).
	 */
	public static function extract( \WP_Post $src, Languages $l, string $target ): array {
		$prep     = self::prepare( $src, $l, $target );
		$warnings = array();

		$existing = $l->translation_of( $src->ID, $target );
		if ( null !== $existing ) {
			$warnings[] = sprintf( 'Post %d already is the %s translation of this post: i18n_create_translation will refuse.', $existing, $target );
		}
		if ( $prep['page']['code_blocks'] > 0 ) {
			$warnings[] = sprintf( '%d code module(s): only the text between tags is translatable; scripts, styles, comments and shortcodes are shown as ⟦n⟧ markers and must be kept.', $prep['page']['code_blocks'] );
		}
		try {
			$footer = ThemeBuilderFooter::info( $l );
		} catch ( ToolError $e ) {
			$footer = null; // The Theme Builder needs Divi's own permissions; the check is informative only.
		}
		if ( null !== $footer && ! isset( $footer['translations'][ $target ] ) ) {
			$warnings[] = sprintf( 'Theme Builder footer (layout %d) has no %s version.', $footer['layout_id'], $target );
		}

		$segments = array();
		foreach ( self::segments( $prep ) as $seg ) {
			$segments[] = array(
				'id'      => $seg['id'],
				'kind'    => $seg['kind'],
				'context' => $seg['context'],
				'text'    => $seg['text'],
			);
		}
		$modules = array();
		foreach ( $prep['globals'] as $g ) {
			$modules[] = array(
				'id'          => $g['id'],
				'title'       => $g['title'],
				'translation' => $g['translation'],
			);
		}

		return array(
			'source_id'       => (int) $src->ID,
			'source_language' => $prep['source_language'],
			'target_language' => $target,
			'source_hash'     => self::digest( $src, $prep ),
			'segments'        => $segments,
			'global_modules'  => $modules,
			'warnings'        => array_merge( $warnings, $prep['notes'] ),
		);
	}

	/**
	 * Hash of everything a translation is computed from: content, title, excerpt, stored Yoast
	 * fields, and the content of each library item that contributes segments, in id order.
	 *
	 * @param \WP_Post  $src    Source post.
	 * @param Languages $l      Language adapter.
	 * @param string    $target Target language code.
	 * @return string sha256 hex.
	 * @throws ToolError See prepare().
	 */
	public static function hash( \WP_Post $src, Languages $l, string $target ): string {
		return self::digest( $src, self::prepare( $src, $l, $target ) );
	}

	/**
	 * Create the translation as a linked draft.
	 *
	 * @param \WP_Post    $src      Source post (editable, translatable type).
	 * @param Languages   $l        Language adapter.
	 * @param string      $target   Target language code (valid).
	 * @param string      $hash     source_hash from extract.
	 * @param array       $segments Segment id => translated text.
	 * @param string|null $slug     Slug of the translation.
	 * @return array{id:int, language:string, status:string, view:string, edit_divi:string, translations:array|\stdClass, created_global_modules:array, warnings:string[]}
	 * @throws ToolError conflict (translation exists, source changed), invalid_argument, forbidden, create_failed.
	 */
	public static function create( \WP_Post $src, Languages $l, string $target, string $hash, array $segments, ?string $slug ): array {
		$prep = self::prepare( $src, $l, $target );

		$existing = $l->translation_of( $src->ID, $target );
		if ( null !== $existing ) {
			throw new ToolError( sprintf( 'Post %d already has a %s translation (post %d); updating an existing translation is not supported.', $src->ID, $target, $existing ), 'conflict', array( 'existing_id' => $existing ) );
		}
		if ( ! hash_equals( self::digest( $src, $prep ), $hash ) ) {
			throw new ToolError( 'The source changed since i18n_extract (content, title, excerpt, SEO fields or a global module); extract again.', 'conflict' );
		}

		$known = array();
		foreach ( self::segments( $prep ) as $seg ) {
			$known[ $seg['id'] ] = $seg;
		}
		$unknown = array();
		foreach ( $segments as $id => $text ) {
			if ( ! isset( $known[ (string) $id ] ) ) {
				$unknown[] = (string) $id;
			} elseif ( ! is_string( $text ) ) {
				throw new ToolError( 'Segment "' . $id . '" must be a string.', 'invalid_argument' );
			}
		}
		if ( $unknown ) {
			throw new ToolError( 'Unknown segment id(s): ' . implode( ', ', $unknown ) . '. Use the ids i18n_extract returned.', 'invalid_argument' );
		}

		$type = PostSupport::post_type( $src->post_type );
		if ( ! current_user_can( $type->cap->create_posts ) ) {
			throw new ToolError( 'You are not allowed to create ' . $type->name . ' content.', 'forbidden' );
		}
		$contributing = array_filter(
			$prep['globals'],
			static function ( $g ) {
				return null !== $g['extract'];
			}
		);
		if ( $contributing ) {
			$library = get_post_type_object( self::LIBRARY_TYPE );
			if ( ! $library || ! current_user_can( $library->cap->create_posts ) ) {
				throw new ToolError( 'You are not allowed to create Divi library items, which the ' . $target . ' versions of the global modules need.', 'forbidden' );
			}
		}

		$warnings = $prep['notes'];
		$missing  = array_keys( array_diff_key( $known, $segments ) );
		if ( $missing ) {
			$warnings[] = 'No translation for ' . count( $missing ) . ' segment(s), kept in the source language: ' . implode( ', ', $missing ) . '.';
		}

		$untranslated = array();
		$link_map     = self::link_map( $l, $target, $untranslated );
		$post_map     = self::post_map( $l, $target, $untranslated );
		$filter       = current_user_can( 'unfiltered_html' ) ? null : 'wp_kses_post';

		$by_scope = self::split( $segments );
		$created  = array();
		$new_ids  = array();
		try {
			$global_map = array();
			foreach ( $prep['globals'] as $g ) {
				if ( null !== $g['translation'] && $g['translation'] !== $g['id'] ) {
					$global_map[ (string) $g['id'] ] = array( (string) $g['translation'], (string) get_post_field( 'post_content', $g['translation'], 'raw' ) );
				}
			}
			foreach ( $contributing as $g ) {
				$result = Segments::apply( (string) $g['post']->post_content, $by_scope['globals'][ $g['id'] ] ?? array(), array(), $link_map, $post_map, $filter );
				foreach ( $result['fallbacks'] as $id ) {
					$warnings[] = self::fallback_warning( 'g' . $g['id'] . '.' . $id );
				}
				$count     = self::pages_using( $g['id'], $l, $target );
				$new_id    = self::insert_library_item( $g['post'], $result['content'] );
				$new_ids[] = $new_id;
				self::link( $l, $g['id'], $new_id, $target );

				$global_map[ (string) $g['id'] ] = array( (string) $new_id, $result['content'] );
				$created[]                       = array(
					'source_id'            => $g['id'],
					'new_id'               => $new_id,
					'title'                => $g['title'],
					'used_in_pages_count'  => $count,
				);
				$warnings[] = sprintf( "Created %s version of global module '%s' (#%d). %d other %s pages still use the original; update them when you are ready.", $target, $g['title'], $new_id, $count, $target );
			}

			$result = Segments::apply( (string) $src->post_content, $by_scope['page'], $global_map, $link_map, $post_map, $filter );
			foreach ( $result['fallbacks'] as $id ) {
				$warnings[] = self::fallback_warning( $id );
			}

			$own = array();
			foreach ( $prep['own'] as $seg ) {
				$own[ $seg['id'] ] = $seg['source'];
				if ( ! array_key_exists( $seg['id'], $by_scope['own'] ) ) {
					continue;
				}
				$value = self::resolve_own( $seg, $by_scope['own'][ $seg['id'] ], $filter );
				if ( null === $value ) {
					$warnings[] = self::fallback_warning( $seg['id'] );
				} else {
					$own[ $seg['id'] ] = $value;
				}
			}

			$new_id    = self::insert_translation( $src, $l, $target, $result['content'], $own, $slug, $warnings );
			$new_ids[] = $new_id;
			self::link( $l, $src->ID, $new_id, $target );
		} catch ( \Throwable $e ) {
			foreach ( $new_ids as $id ) {
				PostSupport::discard_created( $id );
			}
			throw $e instanceof ToolError ? $e : new ToolError( 'Could not create the translation: ' . $e->getMessage(), 'create_failed' );
		}

		if ( $untranslated ) {
			$warnings[] = 'Links kept to content with no ' . $target . ' translation: ' . implode( ', ', array_unique( $untranslated ) ) . '.';
		}

		return array_merge(
			array(
				'id'       => $new_id,
				'language' => $target,
				'status'   => (string) get_post_status( $new_id ),
			),
			DiviSupport::links( $new_id ),
			array(
				'translations'           => I18nSupport::group( $l, $src->ID ),
				'created_global_modules' => $created,
				'warnings'               => $warnings,
			)
		);
	}

	// ----- analysis ----------------------------------------------------------

	/**
	 * Everything extract, hash and create share: the source's own segments, the page segments
	 * and the global modules with their lookup.
	 *
	 * @param \WP_Post  $src    Source post.
	 * @param Languages $l      Language adapter.
	 * @param string    $target Target language code.
	 * @return array{source_language:string, target:string, own:array, own_head:array, seo:array, page:array, globals:array, notes:string[]}
	 * @throws ToolError invalid_argument.
	 */
	private static function prepare( \WP_Post $src, Languages $l, string $target ): array {
		$content = (string) $src->post_content;
		$page    = Segments::extract( $content );
		if ( $page['legacy'] ) {
			throw new ToolError( 'Post ' . $src->ID . ' has Divi 4 (shortcode) content: convert the page to Divi 5 first.', 'invalid_argument' );
		}
		$source_language = $l->post_language( $src->ID );
		if ( null === $source_language ) {
			throw new ToolError( 'Post ' . $src->ID . ' has no language: set it with i18n_set_language first.', 'invalid_argument' );
		}
		if ( $source_language === $target ) {
			throw new ToolError( 'Post ' . $src->ID . ' already is in ' . $target . ': choose another target language.', 'invalid_argument' );
		}

		$own = array();
		if ( '' !== trim( $src->post_title ) ) {
			$own[] = self::own_segment( 't', 'post title', $src->post_title, false );
		}
		if ( '' !== trim( $src->post_excerpt ) ) {
			$own[] = self::own_segment( 'x', 'post excerpt', $src->post_excerpt, false !== strpos( $src->post_excerpt, '<' ) );
		}
		$seo = array();
		if ( Yoast::active() ) {
			$stored = Yoast::stored( $src->ID );
			if ( '' !== trim( $stored['title'] ) ) {
				$seo[] = self::own_segment( 'seo.title', 'Yoast SEO title', $stored['title'], false );
			}
			if ( '' !== trim( $stored['description'] ) ) {
				$seo[] = self::own_segment( 'seo.description', 'Yoast SEO meta description', $stored['description'], false );
			}
		}

		$globals = array();
		$notes   = array();
		$ids     = array_map( 'intval', array_keys( $page['globals'] ) );
		sort( $ids );
		foreach ( $ids as $id ) {
			$g = self::global_module( $id, $l, $target );
			if ( null !== $g['note'] ) {
				$notes[] = $g['note'];
			}
			$globals[] = $g;
		}

		return array(
			'source_language' => $source_language,
			'target'          => $target,
			'own'             => array_merge( $own, $seo ),
			'own_head'        => $own,
			'seo'             => $seo,
			'page'            => $page,
			'globals'         => $globals,
			'notes'           => $notes,
		);
	}

	/**
	 * How one global module is translated: by its existing target version, by creating one from
	 * its own segments, or not at all (with a note).
	 *
	 * @param int       $id     Library item id.
	 * @param Languages $l      Language adapter.
	 * @param string    $target Target language code.
	 * @return array{id:int, title:string, post:\WP_Post|null, translation:int|null, extract:array|null, note:string|null}
	 */
	private static function global_module( int $id, Languages $l, string $target ): array {
		$post = get_post( $id );
		$g    = array(
			'id'          => $id,
			'title'       => $post instanceof \WP_Post ? $post->post_title : '',
			'post'        => null,
			'translation' => null,
			'extract'     => null,
			'note'        => null,
		);
		if ( ! $post instanceof \WP_Post || self::LIBRARY_TYPE !== $post->post_type ) {
			$g['note'] = sprintf( 'Global module #%d was not found in the Divi library; the page keeps pointing to it.', $id );
			return $g;
		}
		if ( ! $l->is_translatable( self::LIBRARY_TYPE ) ) {
			$g['note'] = sprintf( "Global module '%s' (#%d) stays in the source language: the Divi library (%s) is not translatable in %s. Enable it in the language plugin settings to translate global modules.", $g['title'], $id, self::LIBRARY_TYPE, $l->name() );
			return $g;
		}
		$language = $l->post_language( $id );
		if ( $language === $target ) {
			$g['translation'] = $id;
			return $g;
		}
		$translation = $l->translation_of( $id, $target );
		if ( null !== $translation ) {
			$g['translation'] = $translation;
			return $g;
		}
		if ( null === $language ) {
			$g['note'] = sprintf( "Global module '%s' (#%d) has no language: set it with i18n_set_language to translate it. The page keeps the original.", $g['title'], $id );
			return $g;
		}
		$extract = Segments::extract( (string) $post->post_content );
		if ( $extract['legacy'] ) {
			$g['note'] = sprintf( "Global module '%s' (#%d) has Divi 4 (shortcode) content and stays in the source language: convert it to Divi 5 first.", $g['title'], $id );
			return $g;
		}
		$g['post']    = $post;
		$g['extract'] = $extract;
		return $g;
	}

	/**
	 * All segments in output order: title and excerpt, page, SEO, then global modules.
	 *
	 * @param array $prep From prepare().
	 * @return array[] Segments with id, kind, context, text (own ones also source and parts).
	 */
	private static function segments( array $prep ): array {
		$out = array_merge( $prep['own_head'], $prep['page']['segments'], $prep['seo'] );
		foreach ( $prep['globals'] as $g ) {
			if ( null === $g['extract'] ) {
				continue;
			}
			$label = sprintf( "global module %d '%s' (no %s version yet): ", $g['id'], $g['title'], $prep['target'] );
			foreach ( $g['extract']['segments'] as $seg ) {
				$seg['id']      = 'g' . $g['id'] . '.' . $seg['id'];
				$seg['context'] = $label . $seg['context'];
				$out[]          = $seg;
			}
		}
		return $out;
	}

	/**
	 * sha256 over the inputs of a translation.
	 *
	 * @param \WP_Post $src  Source post.
	 * @param array    $prep From prepare().
	 * @return string
	 */
	private static function digest( \WP_Post $src, array $prep ): string {
		$library = array();
		foreach ( $prep['globals'] as $g ) {
			if ( null !== $g['extract'] ) {
				$library[ $g['id'] ] = (string) $g['post']->post_content;
			}
		}
		return hash(
			'sha256',
			serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- byte-exact input of a hash, never unserialized.
				array(
					'content' => (string) $src->post_content,
					'title'   => (string) $src->post_title,
					'excerpt' => (string) $src->post_excerpt,
					'seo'     => Yoast::active() ? Yoast::stored( $src->ID ) : null,
					'library' => $library,
				)
			)
		);
	}

	/**
	 * A segment of the post itself (title, excerpt, SEO field).
	 *
	 * @param string $id      Segment id.
	 * @param string $context Context.
	 * @param string $text    Source text.
	 * @param bool   $html    Whether it holds HTML.
	 * @return array
	 */
	private static function own_segment( string $id, string $context, string $text, bool $html ): array {
		$m = $html ? Protect::mask( $text ) : array( 'text' => $text, 'parts' => array() );
		return array(
			'id'      => $id,
			'kind'    => $html ? 'html' : 'text',
			'context' => $context,
			'text'    => $m['text'],
			'source'  => $text,
			'parts'   => $m['parts'],
		);
	}

	/**
	 * The value to write for a translated own segment, or null to keep the source (blank, or
	 * markers / tags changed), with the same rules as Segments.
	 *
	 * @param array         $seg    Own segment.
	 * @param string        $tr     Translation.
	 * @param callable|null $filter HTML filter.
	 * @return string|null
	 */
	private static function resolve_own( array $seg, string $tr, ?callable $filter ): ?string {
		if ( '' === trim( $tr ) || Protect::tags( $tr ) !== Protect::tags( $seg['text'] ) ) {
			return null;
		}
		if ( 'html' !== $seg['kind'] ) {
			return $tr;
		}
		$out = Protect::unmask( $tr, $seg['parts'] );
		return null !== $out && null !== $filter ? (string) $filter( $out ) : $out;
	}

	/**
	 * Group translations by where they apply: the post's own fields, the page content, or a
	 * global module (prefix g<id>. removed).
	 *
	 * @param array $segments Segment id => text.
	 * @return array{own:array, page:array, globals:array<int,array>}
	 */
	private static function split( array $segments ): array {
		$out = array(
			'own'     => array(),
			'page'    => array(),
			'globals' => array(),
		);
		foreach ( $segments as $id => $text ) {
			$id = (string) $id;
			if ( in_array( $id, array( 't', 'x', 'seo.title', 'seo.description' ), true ) ) {
				$out['own'][ $id ] = $text;
			} elseif ( preg_match( '/^g(\d+)\.(.+)$/s', $id, $m ) ) {
				$out['globals'][ (int) $m[1] ][ $m[2] ] = $text;
			} else {
				$out['page'][ $id ] = $text;
			}
		}
		return $out;
	}

	/**
	 * @param string $id Segment id.
	 * @return string
	 */
	private static function fallback_warning( string $id ): string {
		return 'Segment ' . $id . ' kept the source text: its translation was empty, or dropped or changed a ⟦n⟧ marker or an HTML tag.';
	}

	// ----- links -------------------------------------------------------------

	/**
	 * Map an on-site URL to the permalink of its target translation; null keeps it (mailto:,
	 * tel:, anchors, external URLs, non-posts, untranslated posts, which are recorded).
	 *
	 * @param Languages $l            Language adapter.
	 * @param string    $target       Target language code.
	 * @param string[]  $untranslated Collected untranslated targets.
	 * @return callable
	 */
	private static function link_map( Languages $l, string $target, array &$untranslated ): callable {
		$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$cache = array();
		return static function ( $url ) use ( $l, $target, $home, &$cache, &$untranslated ) {
			$url = (string) $url;
			if ( array_key_exists( $url, $cache ) ) {
				return $cache[ $url ];
			}
			$cache[ $url ] = null;
			$t             = trim( $url );
			if ( '' === $t || '#' === $t[0] ) {
				return null;
			}
			$parts = wp_parse_url( $t );
			if ( false === $parts ) {
				return null;
			}
			if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
				return null; // mailto:, tel:, javascript:...
			}
			if ( isset( $parts['host'] ) ) {
				if ( strtolower( $parts['host'] ) !== $home ) {
					return null;
				}
				$absolute = 0 === strpos( $t, '//' ) ? 'http:' . $t : $t;
			} elseif ( '/' === $t[0] ) {
				$absolute = home_url( $t );
			} elseif ( '?' === $t[0] ) {
				$absolute = home_url( '/' . $t );
			} else {
				return null; // Relative to the current page: cannot be resolved reliably.
			}
			$id = url_to_postid( $absolute );
			if ( $id <= 0 ) {
				return null;
			}
			$translation = $l->translation_of( $id, $target );
			if ( null === $translation ) {
				if ( $l->is_translatable( (string) get_post_type( $id ) ) && $l->post_language( $id ) !== $target ) {
					$untranslated[ $id ] = $url . ' (#' . $id . ')';
				}
				return null;
			}
			if ( $translation === $id ) {
				return null;
			}
			$link = (string) get_permalink( $translation );
			if ( '' === $link ) {
				return null;
			}
			if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
				$link .= '#' . $parts['fragment'];
			}
			$cache[ $url ] = $link;
			return $link;
		};
	}

	/**
	 * Map the post_id of a Divi dynamic post link to its target translation; null keeps it.
	 *
	 * @param Languages $l            Language adapter.
	 * @param string    $target       Target language code.
	 * @param string[]  $untranslated Collected untranslated targets.
	 * @return callable
	 */
	private static function post_map( Languages $l, string $target, array &$untranslated ): callable {
		return static function ( $id ) use ( $l, $target, &$untranslated ) {
			$id   = (int) $id;
			$type = get_post_type( $id );
			if ( ! $type ) {
				return null;
			}
			$translation = $l->translation_of( $id, $target );
			if ( null === $translation ) {
				if ( $l->is_translatable( $type ) && $l->post_language( $id ) !== $target ) {
					$untranslated[ $id ] = get_permalink( $id ) . ' (#' . $id . ')';
				}
				return null;
			}
			return $translation === $id ? null : $translation;
		};
	}

	// ----- writes ------------------------------------------------------------

	/**
	 * Link a new post to its source, mapping the adapter's refusals to tool errors.
	 *
	 * @param Languages $l         Language adapter.
	 * @param int       $source_id Source post id.
	 * @param int       $new_id    New post id.
	 * @param string    $target    Target language code.
	 * @return void
	 * @throws ToolError invalid_argument.
	 */
	private static function link( Languages $l, int $source_id, int $new_id, string $target ): void {
		try {
			$l->link( $source_id, $new_id, $target );
		} catch ( \InvalidArgumentException $e ) {
			throw new ToolError( $e->getMessage(), 'invalid_argument' );
		}
	}

	/**
	 * Insert a post with exact content: the content was built from the stored source plus
	 * segments already filtered (kses) for users without unfiltered_html, so content kses is
	 * not run again over the block markup. Title and excerpt keep WordPress' own filters.
	 *
	 * @param array $postarr Unslashed post array.
	 * @return int
	 * @throws ToolError create_failed.
	 */
	private static function insert( array $postarr ): int {
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		}
		try {
			$id = wp_insert_post( wp_slash( $postarr ), true );
		} finally {
			if ( $kses ) {
				add_filter( 'content_save_pre', 'wp_filter_post_kses' );
			}
		}
		if ( is_wp_error( $id ) || ! $id ) {
			throw new ToolError( 'Could not create the translation: ' . ( is_wp_error( $id ) ? $id->get_error_message() : 'unknown error' ), 'create_failed' );
		}
		return (int) $id;
	}

	/**
	 * Copy a library item with translated content, with the source's status when the user may
	 * publish library items (Divi renders published global modules), else as a draft.
	 *
	 * @param \WP_Post $item    Source library item.
	 * @param string   $content Translated content.
	 * @return int New id.
	 * @throws ToolError create_failed.
	 */
	private static function insert_library_item( \WP_Post $item, string $content ): int {
		$id = self::insert(
			array(
				'post_type'    => $item->post_type,
				'post_status'  => current_user_can( get_post_type_object( $item->post_type )->cap->publish_posts ) ? $item->post_status : 'draft',
				'post_author'  => get_current_user_id(),
				'post_title'   => $item->post_title,
				'post_content' => $content,
			)
		);
		self::copy_meta( $item->ID, $id, false );
		foreach ( get_object_taxonomies( $item->post_type ) as $taxonomy ) {
			if ( in_array( $taxonomy, self::LANGUAGE_TAXONOMIES, true ) ) {
				continue;
			}
			// Divi's library taxonomies (layout type, scope, category) describe the item, not its language.
			$terms = wp_get_object_terms( $item->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_array( $terms ) && $terms ) {
				wp_set_object_terms( $id, array_map( 'intval', $terms ), $taxonomy );
			}
		}
		return $id;
	}

	/**
	 * Insert the translated post as a draft, through PostSupport's plan for type, parent, terms
	 * and featured image, then copy the meta and write the Yoast fields.
	 *
	 * @param \WP_Post  $src      Source post.
	 * @param Languages $l        Language adapter.
	 * @param string    $target   Target language code.
	 * @param string    $content  Translated content.
	 * @param array     $own      Own segment id => value (translated or source).
	 * @param string|null $slug   Slug.
	 * @param string[]  $warnings Warnings (appended to).
	 * @return int New id.
	 * @throws ToolError From the plan or the insert.
	 */
	private static function insert_translation( \WP_Post $src, Languages $l, string $target, string $content, array $own, ?string $slug, array &$warnings ): int {
		$args = array( 'status' => 'draft' );
		if ( isset( $own['t'] ) ) {
			$args['title'] = $own['t'];
		}
		if ( isset( $own['x'] ) ) {
			$args['excerpt'] = $own['x'];
		}
		if ( null !== $slug && '' !== $slug ) {
			$args['slug'] = $slug;
		}
		if ( $src->post_parent > 0 ) {
			$args['parent'] = $l->translation_of( (int) $src->post_parent, $target ) ?? (int) $src->post_parent;
		}

		$terms   = array();
		$dropped = array();
		foreach ( get_object_taxonomies( $src->post_type ) as $taxonomy ) {
			if ( in_array( $taxonomy, self::LANGUAGE_TAXONOMIES, true ) ) {
				continue;
			}
			$source_terms = wp_get_object_terms( $src->ID, $taxonomy );
			if ( ! is_array( $source_terms ) ) {
				continue;
			}
			foreach ( $source_terms as $term ) {
				$mapped = $l->term_translation_of( (int) $term->term_id, $taxonomy, $target );
				if ( null === $mapped ) {
					$dropped[] = $taxonomy . " '" . $term->name . "'";
				} else {
					$terms[ $taxonomy ][] = $mapped;
				}
			}
		}
		if ( $terms ) {
			$args['terms'] = $terms;
		}
		if ( $dropped ) {
			$warnings[] = 'Terms with no ' . $target . ' translation were left out: ' . implode( ', ', $dropped ) . '.';
		}

		$thumbnail = (int) get_post_thumbnail_id( $src );
		if ( $thumbnail > 0 ) {
			if ( wp_attachment_is_image( $thumbnail ) ) {
				$args['featured_media'] = $thumbnail;
			} else {
				$warnings[] = 'The featured image (#' . $thumbnail . ') is not an image attachment and was not copied.';
			}
		}

		$type    = PostSupport::post_type( $src->post_type );
		$plan    = PostSupport::plan( $type, $args, null );
		$postarr = array_merge(
			$plan['post'],
			array(
				'post_type'      => $src->post_type,
				'post_status'    => 'draft',
				'post_author'    => (int) $src->post_author,
				'post_content'   => $content,
				'menu_order'     => (int) $src->menu_order,
				'comment_status' => $src->comment_status,
				'ping_status'    => $src->ping_status,
				'post_password'  => $src->post_password,
			)
		);
		if ( ! isset( $postarr['post_title'] ) ) {
			$postarr['post_title'] = $src->post_title;
		}
		if ( ! isset( $postarr['post_excerpt'] ) ) {
			$postarr['post_excerpt'] = $src->post_excerpt;
		}

		$id = self::insert( $postarr );
		try {
			PostSupport::apply( $id, $plan, true );
			self::copy_meta( $src->ID, $id, Yoast::active() );
			if ( Yoast::active() ) {
				$stored = Yoast::stored( $src->ID );
				Yoast::write(
					$id,
					Yoast::clean( $own['seo.title'] ?? $stored['title'] ),
					Yoast::clean( $own['seo.description'] ?? $stored['description'] )
				);
				Yoast::refresh( $id );
			}
		} catch ( \Throwable $e ) {
			PostSupport::discard_created( $id );
			throw $e;
		}
		return $id;
	}

	/**
	 * Copy meta from one post to another, except the skipped keys.
	 *
	 * @param int  $from    Source post id.
	 * @param int  $to      Target post id.
	 * @param bool $no_seo  Skip the Yoast title and description (written from segments).
	 * @return void
	 */
	private static function copy_meta( int $from, int $to, bool $no_seo ): void {
		$skip = self::SKIPPED_META;
		if ( $no_seo ) {
			$skip[] = '_yoast_wpseo_title';
			$skip[] = '_yoast_wpseo_metadesc';
		}
		foreach ( (array) get_post_meta( $from ) as $key => $values ) {
			$key = (string) $key;
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( self::SKIPPED_META_PREFIXES as $prefix ) {
				if ( 0 === strpos( $key, $prefix ) ) {
					continue 2;
				}
			}
			delete_post_meta( $to, $key );
			foreach ( (array) $values as $value ) {
				add_post_meta( $to, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * How many target-language posts reference a library item (they keep using it).
	 *
	 * @param int       $id     Library item id.
	 * @param Languages $l      Language adapter.
	 * @param string    $target Target language code.
	 * @return int
	 */
	private static function pages_using( int $id, Languages $l, string $target ): int {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"globalModule":"' . $id . '"' ) . '%';
		$ids  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision', %s) AND post_status NOT IN ('trash', 'auto-draft', 'inherit') AND post_content LIKE %s",
				self::LIBRARY_TYPE,
				$like
			)
		);
		$count = 0;
		foreach ( (array) $ids as $post_id ) {
			if ( $l->post_language( (int) $post_id ) === $target ) {
				++$count;
			}
		}
		return $count;
	}

	private function __construct() {}
}
