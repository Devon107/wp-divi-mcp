<?php
/**
 * Shared helpers for the Divi tools: schemas, target checks, the apply pipeline.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Backups;
use WPDM\Divi\DiviRest;
use WPDM\Divi\Globals;
use WPDM\Divi\LayoutInput;
use WPDM\Divi\LayoutTree;
use WPDM\Mcp\Context;
use WPDM\Mcp\ToolError;
use WPDM\Support\Media;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Not a tool: helpers used by the tools in this namespace.
 */
final class DiviSupport {

	/** Statuses that count as published (visitors or logged-in readers see them). */
	const PUBLISHED = array( 'publish', 'future', 'private' );

	/** External image URLs downloaded per apply; the rest are listed in images_skipped. */
	const MAX_EXTERNAL_IMAGES = 30;

	// ----- schemas -----------------------------------------------------------

	/**
	 * Build a tool input schema. With $layout, adds the three layout sources;
	 * the layout tree schema goes under properties.layout and its $defs are
	 * hoisted to the root, so its "#/$defs/..." refs resolve.
	 *
	 * @param array    $props    Tool properties.
	 * @param string[] $required Required properties.
	 * @param bool     $layout   Add layout / markup / portability.
	 * @return array
	 */
	public static function schema( array $props, array $required = array(), bool $layout = false ): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $props,
			'additionalProperties' => false,
		);
		if ( $layout ) {
			$tree = LayoutTree::schema();
			$defs = $tree['$defs'] ?? array();
			unset( $tree['$defs'], $tree['$schema'], $tree['$id'] );
			$tree['description'] = 'Layout tree (preferred input): {"blocks": [{"section": {...}}], "global_colors"?, "global_variables"?, "custom_css"?, "theme"?, "builder_version"?}. Use instead of markup/portability.';

			$schema['properties'] = array_merge(
				array( 'layout' => $tree ),
				LayoutInput::markup_properties(),
				$props
			);
			$schema['$defs'] = array_merge( $defs, $schema['$defs'] ?? array() );
		}
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}

	/**
	 * Options shared by apply_layout and create_page.
	 *
	 * @return array
	 */
	public static function apply_properties(): array {
		return array(
			'sync_globals'      => array(
				'type'        => 'boolean',
				'description' => 'Merge the layout\'s global colors/variables into the site (by id; existing ids are never changed unless overwrite_globals). Default true.',
			),
			'overwrite_globals' => array(
				'type'        => 'boolean',
				'description' => 'Replace existing global values that differ. Built-in colors and ids of another type are never replaced. Default false.',
			),
			'upload_images'     => array(
				'type'        => 'boolean',
				'description' => 'Import embedded images and images hosted on other sites into the media library and rewrite their URLs (at most 30 external URLs per apply; the rest keep their URLs and are listed in images_skipped). Default true.',
			),
		);
	}

	/**
	 * @return array
	 */
	public static function confirm_property(): array {
		return array(
			'type'        => 'boolean',
			'description' => 'Required (true) when the page is published, scheduled or private: the change is visible at once.',
		);
	}

	// ----- targets -----------------------------------------------------------

	/**
	 * A post the current user may edit.
	 *
	 * @param int $id Post id.
	 * @return \WP_Post
	 * @throws ToolError not_found, forbidden, invalid_state.
	 */
	public static function editable_post( int $id ): \WP_Post {
		$post = PostSupport::find_post( $id );
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post->ID . '.', 'forbidden' );
		}
		if ( 'trash' === $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is in the trash. Restore it with restore_post first.', 'invalid_state' );
		}
		return $post;
	}

	/**
	 * Refuse a write to a published/scheduled/private post without confirmation.
	 *
	 * @param \WP_Post $post    Post.
	 * @param bool     $confirm confirm_published.
	 * @return void
	 * @throws ToolError confirmation_required.
	 */
	public static function check_published( \WP_Post $post, bool $confirm ): void {
		if ( ! $confirm && in_array( $post->post_status, self::PUBLISHED, true ) ) {
			throw new ToolError(
				'Post ' . $post->ID . ' is ' . $post->post_status . '; the change would be visible at once. Pass confirm_published=true to proceed, or build the design on a new draft page (divi_create_page).',
				'confirmation_required',
				array(
					'post_id' => $post->ID,
					'status'  => $post->post_status,
				)
			);
		}
	}

	/**
	 * Back up a post's current content and page CSS, read from the database now.
	 *
	 * @param int     $post_id Post id.
	 * @param Context $ctx     Context (connection label).
	 * @param string  $reason  Why (stored in meta).
	 * @return int Backup id.
	 * @throws ToolError backup_failed.
	 */
	public static function backup_post( int $post_id, Context $ctx, string $reason ): int {
		clean_post_cache( $post_id );
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new ToolError( 'Post ' . $post_id . ' not found.', 'not_found' );
		}
		return Backups::save(
			'post_content',
			$post->ID,
			(string) get_post_field( 'post_content', $post->ID, 'raw' ),
			array(
				'post_type'   => $post->post_type,
				'status'      => $post->post_status,
				'custom_css'  => (string) get_post_meta( $post->ID, '_et_pb_custom_css', true ),
				'use_builder' => (string) get_post_meta( $post->ID, '_et_pb_use_builder', true ),
				'reason'      => $reason,
			),
			(string) ( $ctx->connection['label'] ?? '' )
		);
	}

	/**
	 * Store page custom CSS, sanitized like Divi does.
	 *
	 * @param int    $post_id Post id.
	 * @param string $css     CSS.
	 * @return string The stored value.
	 */
	public static function set_page_css( int $post_id, string $css ): string {
		$clean = function_exists( 'et_core_sanitize_custom_css_meta_value' )
			? (string) et_core_sanitize_custom_css_meta_value( $css )
			: wp_strip_all_tags( $css );
		update_post_meta( $post_id, '_et_pb_custom_css', wp_slash( $clean ) );
		return $clean;
	}

	/**
	 * Write content through Divi's own set-layout route (it also turns the
	 * builder on for the post).
	 *
	 * @param int    $post_id Post id.
	 * @param string $content Block markup.
	 * @return void
	 * @throws ToolError From DiviRest.
	 */
	public static function set_layout( int $post_id, string $content ): void {
		// Param names: OutsideVbController.php:1662-1730 (Divi 5.19.1), see DiviRest.
		DiviRest::call(
			'/outside-vb/posts/set-layout',
			array(
				'post_id'        => $post_id,
				'layout_content' => $content,
			)
		);
		clean_post_cache( $post_id );
	}

	/**
	 * @param int $post_id Post id.
	 * @return array{view: string, edit_divi: string}
	 */
	public static function links( int $post_id ): array {
		$view = (string) get_permalink( $post_id );
		return array(
			'view'      => $view,
			'edit_divi' => add_query_arg( 'et_fb', '1', $view ),
		);
	}

	// ----- apply ---------------------------------------------------------------

	/**
	 * Apply a resolved layout to a post. Steps, in this order:
	 * published check -> globals preview (validates globals, nothing written)
	 * -> dry run return -> backup of the current content (read now) -> images
	 * -> globals merge -> page CSS -> set-layout.
	 *
	 * @param \WP_Post $post     Target post (edit_post already checked).
	 * @param array    $resolved LayoutInput::resolve() result.
	 * @param array    $opts     sync_globals, overwrite_globals, upload_images, dry_run, confirm_published.
	 * @param Context  $ctx      Context.
	 * @return array
	 * @throws ToolError On any refusal or failure (data.backup_id once a backup exists).
	 */
	public static function apply( \WP_Post $post, array $resolved, array $opts, Context $ctx ): array {
		$opts = array_merge(
			array(
				'sync_globals'      => true,
				'overwrite_globals' => false,
				'upload_images'     => true,
				'dry_run'           => false,
				'confirm_published' => false,
			),
			$opts
		);
		$label    = (string) ( $ctx->connection['label'] ?? '' );
		$envelope = $resolved['envelope'];
		$content  = $resolved['content'];
		$report   = $resolved['report'];

		self::check_published( $post, (bool) $opts['confirm_published'] );

		$colors    = is_array( $envelope['global_colors'] ?? null ) ? array_values( $envelope['global_colors'] ) : array();
		$variables = is_array( $envelope['global_variables'] ?? null ) ? array_values( $envelope['global_variables'] ) : array();
		$sync      = $opts['sync_globals'] && ( $colors || $variables );
		$preview   = null;
		$changes   = array();
		if ( $sync ) {
			// Validates the globals (invalid_globals) and computes what would change.
			$preview = Globals::merge( $colors, $variables, (bool) $opts['overwrite_globals'], true, $label );
			$changes = array_merge( $preview['colors']['added'], $preview['colors']['overwritten'], $preview['variables']['added'], $preview['variables']['overwritten'] );
			if ( $changes && ! $opts['dry_run'] && ! current_user_can( 'edit_theme_options' ) ) {
				throw new ToolError(
					'This layout adds or changes global colors/variables (' . implode( ', ', $changes ) . '), which needs the edit_theme_options capability. Nothing was changed. Pass sync_globals=false to apply it without them.',
					'forbidden',
					array( 'globals' => $changes )
				);
			}
		}

		$embedded = $opts['upload_images'] ? self::embedded_images( $envelope ) : array();
		$external = $opts['upload_images'] ? self::external_images( $resolved['images'], $embedded ) : array();
		// Downloads are slow: cap them per apply; the rest keep their original URLs.
		$skipped  = array_slice( $external, self::MAX_EXTERNAL_IMAGES );
		$external = array_slice( $external, 0, self::MAX_EXTERNAL_IMAGES );
		$css      = LayoutInput::page_css( $envelope );
		// Media.php: callers must have checked upload_files. Refuse like the globals check.
		$cannot_upload = ( $embedded || $external ) && ! current_user_can( 'upload_files' );
		if ( $cannot_upload && ! $opts['dry_run'] ) {
			throw new ToolError(
				'This layout has ' . ( count( $embedded ) + count( $external ) ) . ' image(s) to import, but your role cannot upload media; pass upload_images=false to keep the original image URLs. Nothing was changed.',
				'forbidden',
				array( 'images' => array_merge( array_keys( $embedded ), $external ) )
			);
		}

		if ( $opts['dry_run'] ) {
			$warnings = $report->warnings;
			if ( $sync && $changes && ! current_user_can( 'edit_theme_options' ) ) {
				$warnings[] = 'applying would change global colors/variables (' . implode( ', ', $changes ) . '), which needs edit_theme_options; it will be refused unless sync_globals=false';
			}
			if ( $cannot_upload ) {
				$warnings[] = 'applying would import images, but your role cannot upload media; it will be refused unless upload_images=false';
			}
			if ( $skipped ) {
				$warnings[] = self::skipped_warning( count( $skipped ) );
			}
			return array(
				'ok'               => true,
				'dry_run'          => true,
				'post_id'          => $post->ID,
				'report'           => $report->to_array(),
				'globals_preview'  => $preview,
				'images_to_upload' => count( $embedded ) + count( $external ),
				'images_skipped'   => $skipped,
				'content_bytes'    => strlen( $content ),
				'page_css'         => '' !== $css,
				'warnings'         => $warnings,
			);
		}

		// First write follows: back up what is stored right now.
		$backup_id = self::backup_post( $post->ID, $ctx, 'before_apply' );
		$old_css   = (string) get_post_meta( $post->ID, '_et_pb_custom_css', true );
		$uploaded  = array();
		$failed    = array();
		$globals   = null;
		$css_set   = false;
		$step      = 'images';
		try {
			foreach ( $embedded as $url => $encoded ) {
				try {
					$media = Media::from_base64( $encoded, self::file_name( $url ) );
				} catch ( \Throwable $e ) { // One image never fails the apply after the backup.
					$failed[] = self::failure( $url, $e );
					continue;
				}
				$content    = self::replace_url( $content, $url, $media['url'] );
				$uploaded[] = array(
					'from' => $url,
					'to'   => $media['url'],
					'id'   => $media['id'],
				);
			}
			foreach ( $external as $url ) {
				try {
					$media = Media::sideload( $url );
				} catch ( \Throwable $e ) { // One image never fails the apply after the backup.
					$failed[] = self::failure( $url, $e );
					continue;
				}
				$content    = self::replace_url( $content, $url, $media['url'] );
				$uploaded[] = array(
					'from' => $url,
					'to'   => $media['url'],
					'id'   => $media['id'],
				);
			}

			if ( $sync ) {
				$step    = 'globals';
				$globals = Globals::merge( $colors, $variables, (bool) $opts['overwrite_globals'], false, $label );
			}
			if ( '' !== $css ) {
				$step    = 'page_css';
				$css_set = true;
				self::set_page_css( $post->ID, $css );
			}
			$step = 'set_layout';
			self::set_layout( $post->ID, $content );
		} catch ( \Throwable $e ) {
			throw self::apply_failure( $e, $post->ID, $step, $backup_id, $globals, $uploaded, $css_set, $old_css );
		}

		$warnings = $report->warnings;
		foreach ( $failed as $f ) {
			$warnings[] = 'image not imported, still loads from ' . $f['url'] . ': ' . $f['error'];
		}
		if ( $skipped ) {
			$warnings[] = self::skipped_warning( count( $skipped ) );
		}
		if ( $globals ) {
			foreach ( array_merge( $globals['colors']['conflicts'], $globals['variables']['conflicts'] ) as $conflict ) {
				$warnings[] = 'global ' . $conflict['id'] . ' already exists on the site with another value or type; the site value was kept';
			}
		}

		return array_merge(
			array(
				'ok'      => true,
				'post_id' => $post->ID,
			),
			self::links( $post->ID ),
			array(
				'backup_id'       => $backup_id,
				'images_uploaded' => $uploaded,
				'images_failed'   => $failed,
				'images_skipped'  => $skipped,
				'globals'         => $globals,
				'page_css'        => '' !== $css,
				'warnings'        => $warnings,
			)
		);
	}

	/**
	 * Turn a failure after the backup into a ToolError that reports every write
	 * already made. The page CSS is put back; globals are not (they are
	 * reported so the user can restore them with divi_restore_backup).
	 *
	 * @param \Throwable $e         Failure.
	 * @param int        $post_id   Post id.
	 * @param string     $step      Step that failed.
	 * @param int        $backup_id Content backup.
	 * @param array|null $globals   Merge report when the merge succeeded.
	 * @param array      $uploaded  Uploaded images.
	 * @param bool       $css_set   Whether the page CSS was (being) written.
	 * @param string     $old_css   Page CSS before the apply.
	 * @return ToolError
	 */
	private static function apply_failure( \Throwable $e, int $post_id, string $step, int $backup_id, ?array $globals, array $uploaded, bool $css_set, string $old_css ): ToolError {
		$data = $e instanceof ToolError ? $e->data() : array();
		$code = $e instanceof ToolError ? $e->error_code() : 'internal_error';

		$globals_backup = $globals['backup_id'] ?? null;
		if ( 'globals' === $step && isset( $data['backup_id'] ) ) {
			$globals_backup = $data['backup_id']; // Globals::merge failed after its own backup.
		}

		$css_restored = false;
		if ( $css_set ) {
			if ( '' === $old_css ) {
				delete_post_meta( $post_id, '_et_pb_custom_css' );
			} else {
				update_post_meta( $post_id, '_et_pb_custom_css', wp_slash( $old_css ) );
			}
			$css_restored = (string) get_post_meta( $post_id, '_et_pb_custom_css', true ) === $old_css;
		}

		$message = $e->getMessage() . ' (failed at: ' . $step . '). The previous content is in backup ' . $backup_id . ' (divi_restore_backup).';
		if ( null !== $globals_backup ) {
			$message .= ' Global colors/variables may have changed; their previous state is in backup ' . $globals_backup . '.';
		}
		if ( $uploaded ) {
			$message .= ' ' . count( $uploaded ) . ' image(s) were already added to the media library.';
		}

		return new ToolError(
			$message,
			$code,
			array_merge(
				$data,
				array(
					'step'              => $step,
					'backup_id'         => $backup_id,
					'globals_backup_id' => $globals_backup,
					'uploaded_ids'      => array_column( $uploaded, 'id' ),
					'css_restored'      => $css_restored,
				)
			)
		);
	}

	// ----- images --------------------------------------------------------------

	/**
	 * Embedded images of an envelope: url => base64.
	 *
	 * @param array $envelope Envelope.
	 * @return array<string, string>
	 */
	private static function embedded_images( array $envelope ): array {
		$out    = array();
		$images = $envelope['images'] ?? array();
		if ( ! is_array( $images ) ) {
			return $out;
		}
		foreach ( $images as $url => $img ) {
			if ( is_string( $url ) && '' !== $url && is_array( $img ) && isset( $img['encoded'] ) && is_string( $img['encoded'] ) && '' !== $img['encoded'] ) {
				$out[ $url ] = $img['encoded'];
			}
		}
		return $out;
	}

	/**
	 * Tree image URLs to download: http(s), hosted elsewhere, not embedded.
	 *
	 * @param string[]              $urls     Tree image URLs.
	 * @param array<string, string> $embedded Embedded images.
	 * @return string[]
	 */
	private static function external_images( array $urls, array $embedded ): array {
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$out  = array();
		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || isset( $embedded[ $url ] ) ) {
				continue;
			}
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || $host === $home ) {
				continue;
			}
			$out[] = $url;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Replace a URL in block markup, also in its JSON-escaped forms.
	 *
	 * @param string $content Markup.
	 * @param string $from    Old URL.
	 * @param string $to      New URL.
	 * @return string
	 */
	private static function replace_url( string $content, string $from, string $to ): string {
		$escape = static function ( $url ) {
			return str_replace( array( '&', '<', '>' ), array( '\\u0026', '\\u003c', '\\u003e' ), $url );
		};
		$pairs = array(
			$from                                         => $to,
			$escape( $from )                              => $escape( $to ),
			str_replace( '/', '\\/', $from )              => str_replace( '/', '\\/', $to ),
			str_replace( '/', '\\/', $escape( $from ) )   => str_replace( '/', '\\/', $escape( $to ) ),
		);
		return strtr( $content, $pairs );
	}

	/**
	 * @param string $url URL.
	 * @return string File name for the upload.
	 */
	private static function file_name( string $url ): string {
		$name = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return '' === $name ? 'image.png' : $name;
	}

	/**
	 * @param string    $url URL.
	 * @param ToolError $e   Error.
	 * @return array{url: string, code: string, error: string}
	 */
	private static function failure( string $url, \Throwable $e ): array {
		return array(
			'url'   => $url,
			'code'  => $e instanceof ToolError ? $e->error_code() : 'internal_error',
			'error' => $e->getMessage(),
		);
	}

	/**
	 * @param int $count Skipped external images.
	 * @return string
	 */
	private static function skipped_warning( int $count ): string {
		return $count . ' external image(s) beyond the first ' . self::MAX_EXTERNAL_IMAGES . ' were not imported and still load from their original URLs (images_skipped); upload them with upload_media and apply again to import them';
	}

	private function __construct() {}
}
