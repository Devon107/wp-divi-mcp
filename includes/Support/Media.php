<?php
/**
 * Safe media import: remote images and base64 uploads.
 *
 * @package WPDM
 */

namespace WPDM\Support;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Adds images to the media library. Used by the upload_media tool and, later,
 * by the layout tools.
 *
 * Both entry points run as the current WordPress user (the attachment author)
 * and do not check capabilities: callers must have verified upload_files.
 *
 * Safety rules, in order of importance:
 * - Remote URLs: http/https only, no credentials, ports 80/443/8080; every host
 *   is resolved and every address must be public (no loopback, private, link
 *   local, CGNAT, multicast, reserved ranges, IPv4-mapped IPv6 of those).
 *   Redirects are followed by hand, at most MAX_REDIRECTS, and each hop goes
 *   through the same check, so a redirect cannot reach an internal address.
 * - The type is decided by the file content, never by the name or the
 *   Content-Type: it must parse as a raster image WordPress accepts; SVG and
 *   anything scriptable is refused. The stored file name always carries the
 *   extension of the detected type.
 * - The validated address is pinned for the request (CURLOPT_RESOLVE), so a
 *   second DNS answer cannot swap in an internal address (DNS rebinding).
 * - At most MAX_BYTES, enforced while downloading and after decoding, and at
 *   most MAX_PIXELS once decoded.
 * - Temp files are removed on every path.
 */
final class Media {

	/**
	 * Largest accepted file: 5 MB.
	 */
	const MAX_BYTES = 5242880;

	/**
	 * Redirects followed for a remote URL.
	 */
	const MAX_REDIRECTS = 3;

	/**
	 * Largest accepted image area (a small file can still decode to gigabytes).
	 */
	const MAX_PIXELS = 50000000;

	/**
	 * Download an image from a URL into the media library.
	 *
	 * @param string $url   http(s) URL of an image.
	 * @param string $alt   Alternative text.
	 * @param string $title Attachment title (defaults to the file name).
	 * @return array{id:int,url:string}
	 * @throws ToolError invalid_url, download_failed, too_large, invalid_type, upload_failed.
	 */
	public static function sideload( string $url, string $alt = '', string $title = '' ): array {
		self::load_admin();
		$tmp = '';
		try {
			$tmp = self::download( trim( $url ) );
			return self::import( $tmp, self::name_from_url( $url ), $alt, $title );
		} finally {
			self::cleanup( $tmp );
		}
	}

	/**
	 * Store base64 encoded image data in the media library.
	 *
	 * @param string $data     Standard base64 (strictly decoded, no data: prefix).
	 * @param string $filename File name; only its base name is used and its
	 *                         extension is replaced by the detected type.
	 * @param string $alt      Alternative text.
	 * @return array{id:int,url:string}
	 * @throws ToolError invalid_argument, invalid_base64, too_large, invalid_type, upload_failed.
	 */
	public static function from_base64( string $data, string $filename, string $alt = '' ): array {
		self::load_admin();

		$filename = sanitize_file_name( wp_basename( $filename ) );
		if ( '' === $filename ) {
			throw new ToolError( 'A file name is required.', 'invalid_argument' );
		}
		// Refuse before decoding what could never fit.
		if ( strlen( $data ) > (int) ceil( self::MAX_BYTES / 3 ) * 4 + 4096 ) {
			throw new ToolError( 'The file is larger than ' . size_format( self::MAX_BYTES ) . '.', 'too_large' );
		}
		$binary = base64_decode( $data, true );
		if ( false === $binary || '' === $binary ) {
			throw new ToolError( 'The data is not valid base64.', 'invalid_base64' );
		}
		if ( strlen( $binary ) > self::MAX_BYTES ) {
			throw new ToolError( 'The file is larger than ' . size_format( self::MAX_BYTES ) . '.', 'too_large' );
		}

		$tmp = wp_tempnam( $filename );
		if ( ! $tmp ) {
			throw new ToolError( 'Could not create a temporary file.', 'upload_failed' );
		}
		try {
			if ( false === file_put_contents( $tmp, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				throw new ToolError( 'Could not write the temporary file.', 'upload_failed' );
			}
			unset( $binary );
			return self::import( $tmp, $filename, $alt, '' );
		} finally {
			self::cleanup( $tmp );
		}
	}

	// ----- download --------------------------------------------------------

	/**
	 * Download to a temp file, validating the URL and every redirect hop.
	 *
	 * @param string $url URL.
	 * @return string Temp file path (caller removes it).
	 * @throws ToolError On any failure.
	 */
	private static function download( string $url ): string {
		$current = $url;
		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$pin = self::assert_public_url( $current );

			$tmp = wp_tempnam( $current );
			if ( ! $tmp ) {
				throw new ToolError( 'Could not create a temporary file.', 'download_failed' );
			}

			$pinner = null;
			if ( null !== $pin ) {
				$pinner = static function ( $handle ) use ( $pin ) {
					curl_setopt( $handle, CURLOPT_RESOLVE, array( $pin['host'] . ':' . $pin['port'] . ':' . $pin['address'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				};
				add_action( 'http_api_curl', $pinner, 10, 1 );
			}
			try {
				$response = wp_safe_remote_get(
					$current,
					array(
						'timeout'             => 20,
						'redirection'         => 0, // Followed below so each hop is validated.
						'stream'              => true,
						'filename'            => $tmp,
						'reject_unsafe_urls'  => true,
						'limit_response_size' => self::MAX_BYTES + 1,
					)
				);
			} finally {
				if ( null !== $pinner ) {
					remove_action( 'http_api_curl', $pinner, 10 );
				}
			}
			if ( is_wp_error( $response ) ) {
				self::cleanup( $tmp );
				throw new ToolError( 'The file could not be downloaded.', 'download_failed' );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				self::cleanup( $tmp );
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( is_array( $location ) ) {
					$location = reset( $location );
				}
				if ( ! is_string( $location ) || '' === trim( $location ) ) {
					throw new ToolError( 'The server sent a redirect without a location.', 'download_failed' );
				}
				$current = (string) \WP_Http::make_absolute_url( trim( $location ), $current );
				continue;
			}
			if ( 200 !== $code ) {
				self::cleanup( $tmp );
				throw new ToolError( 'The server answered HTTP ' . $code . '.', 'download_failed' );
			}

			clearstatcache( true, $tmp );
			if ( (int) filesize( $tmp ) > self::MAX_BYTES ) {
				self::cleanup( $tmp );
				throw new ToolError( 'The file is larger than ' . size_format( self::MAX_BYTES ) . '.', 'too_large' );
			}
			return $tmp;
		}
		throw new ToolError( 'Too many redirects.', 'download_failed' );
	}

	/**
	 * Reject anything that is not a plain http(s) URL to a public address.
	 *
	 * @param string $url URL.
	 * @return array{host:string,port:int,address:string}|null The address to pin for
	 *         the request, or null when the host is already an IP literal.
	 * @throws ToolError invalid_url.
	 */
	private static function assert_public_url( string $url ): ?array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			throw new ToolError( 'The URL is not valid.', 'invalid_url' );
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			throw new ToolError( 'Only http and https URLs are allowed.', 'invalid_url' );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new ToolError( 'URLs with credentials are not allowed.', 'invalid_url' );
		}
		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443, 8080 ), true ) ) {
			throw new ToolError( 'Only ports 80, 443 and 8080 are allowed.', 'invalid_url' );
		}

		$host = strtolower( rtrim( trim( $parts['host'], '[]' ), '.' ) );
		if ( '' === $host ) {
			throw new ToolError( 'The URL is not valid.', 'invalid_url' );
		}
		// Numeric shorthand such as 2130706433 or 0x7f.1 is an IP in disguise.
		if ( ! filter_var( $host, FILTER_VALIDATE_IP ) && preg_match( '/^[0-9a-fx.]+$/i', $host ) && preg_match( '/^(0x[0-9a-f]+|[0-9]+)(\.|$)/i', $host ) ) {
			throw new ToolError( 'The host is not allowed.', 'invalid_url' );
		}

		$ips = self::resolve( $host );
		if ( ! $ips ) {
			throw new ToolError( 'The host could not be resolved.', 'invalid_url' );
		}
		foreach ( $ips as $ip ) {
			if ( ! self::is_public_ip( $ip ) ) {
				throw new ToolError( 'The URL points to a private or reserved address.', 'invalid_url' );
			}
		}

		// WordPress' own check (scheme, port allowlist, its private ranges) on top.
		if ( false === wp_http_validate_url( $url ) ) {
			throw new ToolError( 'The URL was rejected as unsafe.', 'invalid_url' );
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return null;
		}
		$address = $ips[0];
		return array(
			'host'    => $host,
			'port'    => isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === strtolower( $parts['scheme'] ) ? 443 : 80 ),
			'address' => false !== strpos( $address, ':' ) ? '[' . $address . ']' : $address,
		);
	}

	/**
	 * All addresses a host name resolves to (A and AAAA).
	 *
	 * @param string $host Host or IP literal.
	 * @return string[]
	 */
	private static function resolve( string $host ): array {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}
		$ips  = array();
		$ipv4 = gethostbynamel( $host );
		if ( is_array( $ipv4 ) ) {
			$ips = $ipv4;
		}
		$ipv6 = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $ipv6 ) ) {
			foreach ( $ipv6 as $record ) {
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6'];
				}
			}
		}
		return array_values( array_unique( $ips ) );
	}

	/**
	 * Whether an address is routable on the public internet.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return bool
	 */
	private static function is_public_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		if ( false !== strpos( $ip, ':' ) ) {
			$packed = inet_pton( $ip );
			if ( false === $packed || 16 !== strlen( $packed ) ) {
				return false;
			}
			$zeros = str_repeat( "\0", 10 );
			if ( substr( $packed, 0, 12 ) === $zeros . "\xff\xff" ) {
				return self::is_public_ip( (string) inet_ntop( substr( $packed, 12 ) ) ); // IPv4-mapped.
			}
			if ( substr( $packed, 0, 12 ) === "\0\x64\xff\x9b" . str_repeat( "\0", 8 ) ) {
				return self::is_public_ip( (string) inet_ntop( substr( $packed, 12 ) ) ); // NAT64.
			}
			if ( substr( $packed, 0, 12 ) === str_repeat( "\0", 12 ) ) {
				return false; // ::/96 including :: and ::1.
			}
			$b0 = ord( $packed[0] );
			$b1 = ord( $packed[1] );
			if ( 0xfc === ( $b0 & 0xfe ) ) {
				return false; // fc00::/7 unique local.
			}
			if ( 0xfe === $b0 && 0x80 === ( $b1 & 0x80 ) ) {
				return false; // fe80::/9 link-local and site-local.
			}
			if ( 0xff === $b0 ) {
				return false; // Multicast.
			}
			if ( 0x20 === $b0 && 0x01 === $b1 && 0x0d === ord( $packed[2] ) && 0xb8 === ord( $packed[3] ) ) {
				return false; // 2001:db8::/32 documentation.
			}
			return true;
		}

		$long = ip2long( $ip );
		if ( false === $long ) {
			return false;
		}
		$long &= 0xFFFFFFFF;
		$nets  = array(
			array( 0x64400000, 0xFFC00000 ), // 100.64.0.0/10 carrier-grade NAT.
			array( 0xC0000000, 0xFFFFFF00 ), // 192.0.0.0/24 IETF protocol assignments.
			array( 0xC6120000, 0xFFFE0000 ), // 198.18.0.0/15 benchmarking.
			array( 0xE0000000, 0xE0000000 ), // 224.0.0.0/3 multicast and reserved.
		);
		foreach ( $nets as $net ) {
			if ( ( $long & $net[1] ) === $net[0] ) {
				return false;
			}
		}
		return true;
	}

	// ----- import ----------------------------------------------------------

	/**
	 * Verify the file content is an image and add it to the media library.
	 *
	 * @param string $tmp      Temp file (the caller removes it).
	 * @param string $name     Wanted file name.
	 * @param string $alt      Alt text.
	 * @param string $title    Title.
	 * @return array{id:int,url:string}
	 * @throws ToolError invalid_type or upload_failed.
	 */
	private static function import( string $tmp, string $name, string $alt, string $title ): array {
		$name = self::checked_name( $tmp, $name );

		$post_data = array();
		$id        = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0,
			'' !== trim( $title ) ? sanitize_text_field( $title ) : null,
			$post_data
		);
		if ( is_wp_error( $id ) ) {
			throw new ToolError( 'WordPress could not store the file: ' . $id->get_error_message(), 'upload_failed' );
		}

		if ( '' !== trim( $alt ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $alt ) ) );
		}
		return array(
			'id'  => (int) $id,
			'url' => (string) wp_get_attachment_url( $id ),
		);
	}

	/**
	 * Decide the type from the file content and return a file name whose
	 * extension matches it.
	 *
	 * @param string $tmp  Temp file.
	 * @param string $name Wanted name.
	 * @return string Safe file name.
	 * @throws ToolError invalid_type unless the content is an accepted image.
	 */
	private static function checked_name( string $tmp, string $name ): string {
		$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $tmp ) : @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mime = is_array( $info ) && ! empty( $info['mime'] ) ? (string) $info['mime'] : '';
		if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
			throw new ToolError( 'The file is not an image.', 'invalid_type' );
		}

		$width  = (int) ( $info[0] ?? 0 );
		$height = (int) ( $info[1] ?? 0 );
		if ( $width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS ) {
			throw new ToolError( 'The image dimensions are not acceptable (at most ' . number_format_i18n( self::MAX_PIXELS ) . ' pixels).', 'too_large' );
		}

		$ext = '';
		foreach ( get_allowed_mime_types() as $extensions => $allowed ) {
			if ( $allowed === $mime ) {
				$ext = strtolower( (string) strtok( (string) $extensions, '|' ) );
				break;
			}
		}
		if ( '' === $ext ) {
			throw new ToolError( 'Images of type ' . $mime . ' are not allowed.', 'invalid_type' );
		}

		$base = sanitize_file_name( pathinfo( wp_basename( $name ), PATHINFO_FILENAME ) );
		$base = trim( $base, '.' );
		$name = ( '' === $base ? 'image' : $base ) . '.' . $ext;

		$check = wp_check_filetype_and_ext( $tmp, $name );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || 0 !== strpos( (string) $check['type'], 'image/' ) ) {
			throw new ToolError( 'The file is not an allowed image.', 'invalid_type' );
		}
		return $name;
	}

	/**
	 * @param string $url URL.
	 * @return string Base name of the URL path, or a default.
	 */
	private static function name_from_url( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$base = is_string( $path ) ? wp_basename( $path ) : '';
		return '' === $base ? 'image' : $base;
	}

	/**
	 * @param string $tmp Temp file path, may be empty.
	 * @return void
	 */
	private static function cleanup( string $tmp ): void {
		if ( '' !== $tmp && file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
	}

	/**
	 * Load the admin APIs the import needs.
	 *
	 * @return void
	 */
	private static function load_admin(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
