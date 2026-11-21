<?php
/**
 * Tool: update_options.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Writes a fixed allowlist of site settings. Any other option name is refused,
 * so this can never be used to change URLs, roles, plugins or credentials.
 */
final class UpdateOptions extends Tool {

	public function name(): string {
		return 'update_options';
	}

	public function description(): string {
		return 'Change site settings. Only these keys are accepted: ' . implode( ', ', SiteSupport::OPTIONS ) . '. Any other key (permalink_structure is read-only) is refused and nothing is written. Values are validated first: show_on_front is posts or page; page_on_front and page_for_posts are 0 or the ID of a published page (and differ); posts_per_page is 1-100; timezone_string is a valid identifier such as Europe/Madrid.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'options' => array(
					'type'                 => 'object',
					'description'          => 'Settings to change: {"blogname": "My site", "posts_per_page": 12}.',
					'properties'           => array(
						'blogname'        => array( 'type' => 'string' ),
						'blogdescription' => array( 'type' => 'string' ),
						'timezone_string' => array( 'type' => 'string' ),
						'date_format'     => array( 'type' => 'string' ),
						'time_format'     => array( 'type' => 'string' ),
						'show_on_front'   => array(
							'type' => 'string',
							'enum' => array( 'posts', 'page' ),
						),
						'page_on_front'   => array( 'type' => 'integer' ),
						'page_for_posts'  => array( 'type' => 'integer' ),
						'posts_per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					),
					'additionalProperties' => false,
				),
			),
			'required'             => array( 'options' ),
			'additionalProperties' => false,
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'manage_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new ToolError( 'You are not allowed to change site settings.', 'forbidden' );
		}

		$options = $args['options'];
		if ( ! $options ) {
			throw new ToolError( 'Pass at least one option to change.', 'invalid_argument' );
		}

		// 1. Allowlist first: one stray key and nothing is written.
		$refused = array();
		foreach ( array_keys( $options ) as $key ) {
			if ( ! in_array( (string) $key, SiteSupport::OPTIONS, true ) ) {
				$refused[] = (string) $key;
			}
		}
		if ( $refused ) {
			$note = in_array( 'permalink_structure', $refused, true ) ? ' permalink_structure is read-only.' : '';
			throw new ToolError( 'Option(s) not allowed: ' . implode( ', ', $refused ) . '.' . $note . ' Allowed: ' . implode( ', ', SiteSupport::OPTIONS ) . '.', 'not_allowed' );
		}

		// 2. Validate every value, then the combination, still without writing.
		$clean = array();
		foreach ( $options as $key => $value ) {
			$clean[ $key ] = $this->clean( (string) $key, $value );
		}
		$front = array_key_exists( 'page_on_front', $clean ) ? $clean['page_on_front'] : (int) get_option( 'page_on_front' );
		$posts = array_key_exists( 'page_for_posts', $clean ) ? $clean['page_for_posts'] : (int) get_option( 'page_for_posts' );
		if ( $front > 0 && $front === $posts ) {
			throw new ToolError( 'page_on_front and page_for_posts must be different pages.', 'invalid_argument' );
		}

		// 3. Write, restoring the previous values if something fails half way.
		$previous = array();
		foreach ( $clean as $key => $value ) {
			$previous[ $key ] = get_option( $key );
			update_option( $key, $value );
			if ( (string) get_option( $key ) !== (string) $this->stored_form( $key, $value ) ) {
				foreach ( $previous as $name => $old ) {
					update_option( $name, $old );
				}
				throw new ToolError( 'WordPress did not accept the value for "' . $key . '".', 'update_failed' );
			}
		}

		return array(
			'updated' => array_keys( $clean ),
			'options' => SiteSupport::options(),
		);
	}

	/**
	 * Validate and normalise one value.
	 *
	 * @param string $key   Allowlisted option name.
	 * @param mixed  $value Raw value.
	 * @return string|int
	 * @throws ToolError invalid_argument.
	 */
	private function clean( string $key, $value ) {
		switch ( $key ) {
			case 'blogname':
				return $this->text( $key, $value, 1, 255 );
			case 'blogdescription':
				return $this->text( $key, $value, 0, 500 );
			case 'date_format':
			case 'time_format':
				return $this->text( $key, $value, 1, 100 );
			case 'timezone_string':
				if ( ! is_string( $value ) || ! in_array( $value, timezone_identifiers_list(), true ) ) {
					throw new ToolError( 'timezone_string must be a valid identifier such as Europe/Madrid or UTC.', 'invalid_argument' );
				}
				return $value;
			case 'show_on_front':
				if ( ! in_array( $value, array( 'posts', 'page' ), true ) ) {
					throw new ToolError( 'show_on_front must be "posts" or "page".', 'invalid_argument' );
				}
				return $value;
			case 'posts_per_page':
				if ( ! is_int( $value ) || $value < 1 || $value > 100 ) {
					throw new ToolError( 'posts_per_page must be an integer from 1 to 100.', 'invalid_argument' );
				}
				return $value;
			default: // page_on_front, page_for_posts.
				if ( ! is_int( $value ) || $value < 0 ) {
					throw new ToolError( $key . ' must be 0 or the ID of a published page.', 'invalid_argument' );
				}
				if ( $value > 0 ) {
					$page = get_post( $value );
					if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
						throw new ToolError( $key . ' must be the ID of a published page.', 'invalid_argument' );
					}
				}
				return $value;
		}
	}

	/**
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 * @param int    $min   Minimum length.
	 * @param int    $max   Maximum length.
	 * @return string
	 * @throws ToolError invalid_argument.
	 */
	private function text( string $key, $value, int $min, int $max ): string {
		if ( ! is_string( $value ) ) {
			throw new ToolError( $key . ' must be a string.', 'invalid_argument' );
		}
		$value = trim( sanitize_text_field( $value ) );
		$len   = mb_strlen( $value );
		if ( $len < $min || $len > $max ) {
			throw new ToolError( $key . ' must be ' . ( $min > 0 ? 'between ' . $min . ' and ' . $max : 'at most ' . $max ) . ' characters.', 'invalid_argument' );
		}
		return $value;
	}

	/**
	 * What get_option() returns for a value after WordPress sanitised it.
	 *
	 * @param string     $key   Option name.
	 * @param string|int $value Validated value.
	 * @return string|int
	 */
	private function stored_form( string $key, $value ) {
		return sanitize_option( $key, $value );
	}
}
