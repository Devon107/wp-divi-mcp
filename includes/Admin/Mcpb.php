<?php
/**
 * Claude Desktop extension (.mcpb) for one connection.
 *
 * The bundle holds a manifest and a copy of assets/mcpb/server.js, a local
 * stdio-to-HTTP bridge. Claude Desktop runs it on the user's computer, so the
 * site is reached from there instead of from Anthropic's cloud. That avoids
 * hosts (WP Engine) whose edge blocks the cloud connector's sign-in client.
 *
 * @package WPDM
 */

namespace WPDM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the .mcpb zip in memory.
 */
final class Mcpb {

	const PREFIX     = 'wp-divi-mcp-';
	const MAX_NAME   = 64;
	const SERVER_SRC = 'assets/mcpb/server.js';

	/**
	 * Whether bundles can be built on this server.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return (bool) apply_filters( 'wpdm_mcpb_available', class_exists( 'ZipArchive' ) );
	}

	/**
	 * Extension name: wp-divi-mcp-<host>-<label>-<connection id>, [a-z0-9-], at most 64 chars.
	 *
	 * The connection id keeps names unique, so several sites (or connections)
	 * can be installed side by side in one Claude Desktop.
	 *
	 * @param string $label         Connection label.
	 * @param string $connection_id Connection id.
	 * @return string
	 */
	public static function name( string $label, string $connection_id ): string {
		$id    = self::slug( $connection_id );
		$host  = self::slug( (string) self::host() );
		$label = self::slug( $label );

		$middle = trim( $host . '-' . $label, '-' );
		$room   = self::MAX_NAME - strlen( self::PREFIX ) - strlen( $id ) - 1;
		$middle = trim( substr( $middle, 0, max( 0, $room ) ), '-' );

		return self::PREFIX . ( '' !== $middle ? $middle . '-' : '' ) . $id;
	}

	/**
	 * Download file name.
	 *
	 * @param string $label         Connection label.
	 * @param string $connection_id Connection id.
	 * @return string
	 */
	public static function filename( string $label, string $connection_id ): string {
		return self::name( $label, $connection_id ) . '.mcpb';
	}

	/**
	 * Build the bundle.
	 *
	 * @param string $url           Connection URL (contains the key).
	 * @param string $label         Connection label.
	 * @param string $connection_id Connection id.
	 * @return string Zip bytes.
	 * @throws \RuntimeException When the zip cannot be built.
	 */
	public static function build( string $url, string $label, string $connection_id ): string {
		if ( ! self::available() || ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( 'ZipArchive is not available.' );
		}
		/**
		 * Path of the Desktop server script copied into the bundle.
		 *
		 * @param string $path Absolute path.
		 */
		$path   = apply_filters( 'wpdm_mcpb_server_path', WPDM_DIR . self::SERVER_SRC );
		$server = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		if ( false === $server ) {
			throw new \RuntimeException( 'Cannot read the Desktop server script.' );
		}
		$manifest = wp_json_encode( self::manifest( $url, $label, $connection_id ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $manifest ) {
			throw new \RuntimeException( 'Cannot encode the manifest.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = wp_tempnam( 'wpdm-mcpb' );
		$zip  = new \ZipArchive();
		try {
			if ( true !== $zip->open( $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				throw new \RuntimeException( 'Cannot create the zip.' );
			}
			$zip->addFromString( 'manifest.json', $manifest );
			$zip->addFromString( 'server/index.js', $server );
			if ( ! $zip->close() ) {
				throw new \RuntimeException( 'Cannot write the zip.' );
			}
			$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- temp file we just wrote.
			if ( false === $bytes ) {
				throw new \RuntimeException( 'Cannot read the zip.' );
			}
			return $bytes;
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * The manifest (MCPB manifest_version 0.3).
	 *
	 * @param string $url           Connection URL.
	 * @param string $label         Connection label.
	 * @param string $connection_id Connection id.
	 * @return array
	 */
	private static function manifest( string $url, string $label, string $connection_id ): array {
		$host    = self::host();
		$display = '' !== trim( $label ) ? $label : $host;
		return array(
			'manifest_version' => '0.3',
			'name'             => self::name( $label, $connection_id ),
			'display_name'     => sprintf( 'Divi MCP – %s (%s)', $display, $host ),
			'version'          => WPDM_VERSION,
			'description'      => sprintf( 'Connects Claude Desktop to %s through the WP Divi MCP plugin.', $host ),
			'author'           => array( 'name' => 'WP Divi MCP' ),
			'server'           => array(
				'type'        => 'node',
				'entry_point' => 'server/index.js',
				'mcp_config'  => array(
					'command' => 'node',
					'args'    => array( '${__dirname}/server/index.js' ),
					'env'     => array(
						'WPDM_URL'     => $url,
						'WPDM_VERSION' => WPDM_VERSION,
					),
				),
			),
			'compatibility'    => array(
				'platforms' => array( 'darwin', 'win32', 'linux' ),
			),
		);
	}

	/**
	 * Site host.
	 *
	 * @return string
	 */
	private static function host(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? $host : 'wordpress';
	}

	/**
	 * Lowercase [a-z0-9-] slug without repeated or edge hyphens.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function slug( string $text ): string {
		$slug = strtolower( remove_accents( $text ) );
		$slug = (string) preg_replace( '/[^a-z0-9]+/', '-', $slug );
		return trim( $slug, '-' );
	}
}
