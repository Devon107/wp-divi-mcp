<?php
/**
 * Settings > Divi MCP: connections, endpoint switch and activity log.
 *
 * @package WPDM
 */

namespace WPDM\Admin;

use WPDM\Auth\Tokens;
use WPDM\Log\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen. Form handlers are separate static methods (handle_*) that
 * redirect but do not exit, so tests can call them directly; the
 * admin_post_wpdm_* callbacks (post_*) add the exit.
 */
class SettingsPage {

	const SLUG          = 'wp-divi-mcp';
	const CAPABILITY    = 'manage_options';
	const COMPANION     = 'divi5-mcp-companion/divi5-mcp-companion.php';
	const NEW_CONN_TTL  = 300; // Seconds the one-time URL stays retrievable.
	const EXPIRY_CHOICE = array( 0, 30, 90 ); // Days; 0 = never.
	const NOTICE_ARG    = 'wpdm_notice';

	/**
	 * Hook suffix of the page, set by add_menu().
	 *
	 * @var string|false
	 */
	private static $hook = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_wpdm_create', array( self::class, 'post_create' ) );
		add_action( 'admin_post_wpdm_revoke', array( self::class, 'post_revoke' ) );
		add_action( 'admin_post_wpdm_settings', array( self::class, 'post_settings' ) );
	}

	/**
	 * @return void
	 */
	public static function add_menu(): void {
		self::$hook = add_options_page(
			__( 'Divi MCP', 'wp-divi-mcp' ),
			__( 'Divi MCP', 'wp-divi-mcp' ),
			self::CAPABILITY,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Load the stylesheet on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( false === self::$hook || self::$hook !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'wpdm-admin', plugins_url( 'assets/admin.css', WPDM_FILE ), array(), WPDM_VERSION );
	}

	/**
	 * @return string URL of this page, optionally with a notice code.
	 */
	public static function page_url( string $notice = '' ): string {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $notice ) {
			$args[ self::NOTICE_ARG ] = $notice;
		}
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}

	// ------------------------------------------------------------------
	// Form handlers.
	// ------------------------------------------------------------------

	/**
	 * admin_post_wpdm_create.
	 *
	 * @return void
	 */
	public static function post_create(): void {
		self::handle_create();
		exit;
	}

	/**
	 * admin_post_wpdm_revoke.
	 *
	 * @return void
	 */
	public static function post_revoke(): void {
		self::handle_revoke();
		exit;
	}

	/**
	 * admin_post_wpdm_settings.
	 *
	 * @return void
	 */
	public static function post_settings(): void {
		self::handle_settings();
		exit;
	}

	/**
	 * Create a connection, stash its URL for one display, and redirect.
	 *
	 * The token is never placed in the redirect URL: it travels only through
	 * a short-lived transient scoped to the current user.
	 *
	 * @return void
	 */
	public static function handle_create(): void {
		self::authorize( 'wpdm_create' );

		$label   = sanitize_text_field( self::field( 'label' ) );
		$user_id = absint( self::field( 'user_id' ) );
		$mode    = sanitize_key( self::field( 'mode' ) );
		$days    = absint( self::field( 'expires' ) );

		if ( '' === $label ) {
			self::redirect( 'err_label' );
			return;
		}
		if ( ! self::can_mint_for( $user_id ) ) {
			self::redirect( 'err_user' );
			return;
		}
		if ( ! in_array( $mode, Tokens::MODES, true ) ) {
			self::redirect( 'err_mode' );
			return;
		}
		if ( ! in_array( $days, self::EXPIRY_CHOICE, true ) ) {
			self::redirect( 'err_expiry' );
			return;
		}

		$expires = $days > 0 ? time() + $days * DAY_IN_SECONDS : null;
		$created = Tokens::create( $label, $user_id, $mode, $expires );

		set_transient(
			self::transient_key(),
			array(
				'url'   => $created['url'],
				'token' => $created['token'],
				'label' => $label,
			),
			self::NEW_CONN_TTL
		);

		self::redirect( 'created' );
	}

	/**
	 * Whether the current user may create a connection that acts as $user_id.
	 *
	 * The target must be the current user, or a member of this site whom the
	 * current user can edit. A super admin target needs a super admin minting
	 * it (on multisite a site admin must never get a network-wide token). The
	 * target must always be able to edit posts.
	 *
	 * @param int $user_id Target user.
	 * @return bool
	 */
	public static function can_mint_for( int $user_id ): bool {
		if ( $user_id <= 0 || ! get_userdata( $user_id ) || ! user_can( $user_id, 'edit_posts' ) ) {
			return false;
		}
		$me = get_current_user_id();
		if ( $user_id === $me ) {
			return true;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || ! is_user_member_of_blog( $user_id ) ) {
			return false;
		}
		if ( is_super_admin( $user_id ) && ! is_super_admin( $me ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Revoke a connection and redirect.
	 *
	 * @return void
	 */
	public static function handle_revoke(): void {
		self::authorize( 'wpdm_revoke' );

		$id = sanitize_key( self::field( 'connection_id' ) );
		self::redirect( '' !== $id && Tokens::revoke( $id ) ? 'revoked' : 'err_missing' );
	}

	/**
	 * Save the "Disable endpoint" switch and redirect.
	 *
	 * @return void
	 */
	public static function handle_settings(): void {
		self::authorize( 'wpdm_settings' );

		update_option( 'wpdm_disabled', empty( $_POST['wpdm_disabled'] ) ? 0 : 1 );
		self::redirect( 'saved' );
	}

	/**
	 * A POST field as a string ('' when absent or not a string, e.g. an array).
	 * Callers sanitize; the nonce was verified by authorize() first.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private static function field( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() ran check_admin_referer().
		$value = $_POST[ $name ] ?? '';
		return is_string( $value ) ? wp_unslash( $value ) : '';
	}

	/**
	 * Capability then nonce; both end in wp_die().
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function authorize( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-divi-mcp' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * @param string $notice Notice code for the page to show.
	 * @return void
	 */
	private static function redirect( string $notice ): void {
		wp_safe_redirect( self::page_url( $notice ) );
	}

	/**
	 * @return string Transient holding the connection URL to show once.
	 */
	private static function transient_key(): string {
		return 'wpdm_new_conn_' . get_current_user_id();
	}

	// ------------------------------------------------------------------
	// Rendering.
	// ------------------------------------------------------------------

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-divi-mcp' ), '', array( 'response' => 403 ) );
		}

		// Read-and-delete: the URL (and token) is shown on exactly one render.
		$fresh = get_transient( self::transient_key() );
		if ( false !== $fresh ) {
			delete_transient( self::transient_key() );
		}

		echo '<div class="wrap wpdm-wrap">';
		echo '<h1>' . esc_html__( 'Divi MCP', 'wp-divi-mcp' ) . '</h1>';

		self::render_notices( is_array( $fresh ) );
		if ( is_array( $fresh ) ) {
			self::render_new_connection( $fresh );
		}
		self::render_connections();
		self::render_new_form();
		self::render_endpoint_switch();
		self::render_activity();

		echo '</div>';
	}

	/**
	 * @param bool $has_fresh Whether a new connection is displayed on this render.
	 * @return void
	 */
	private static function render_notices( bool $has_fresh ): void {
		if ( self::companion_active() ) {
			self::notice(
				'warning',
				__( 'The Divi 5 MCP Companion plugin is active. Deactivate it: WP Divi MCP replaces it.', 'wp-divi-mcp' )
			);
		}
		if ( 0 !== strpos( rest_url(), 'https://' ) ) {
			self::notice(
				'warning',
				__( 'This site is not served over HTTPS. Connection URLs contain a secret token, so the endpoint refuses plain HTTP unless explicitly allowed. Enable HTTPS before connecting Claude.', 'wp-divi-mcp' )
			);
		}

		$messages = array(
			'created'     => array( 'success', __( 'Connection created.', 'wp-divi-mcp' ) ),
			'revoked'     => array( 'success', __( 'Connection revoked.', 'wp-divi-mcp' ) ),
			'saved'       => array( 'success', __( 'Settings saved.', 'wp-divi-mcp' ) ),
			'err_label'   => array( 'error', __( 'Enter a label for the connection.', 'wp-divi-mcp' ) ),
			'err_user'    => array( 'error', __( 'Choose a user who can edit posts and whom you can manage on this site.', 'wp-divi-mcp' ) ),
			'err_mode'    => array( 'error', __( 'Choose a valid mode.', 'wp-divi-mcp' ) ),
			'err_expiry'  => array( 'error', __( 'Choose a valid expiry.', 'wp-divi-mcp' ) ),
			'err_missing' => array( 'error', __( 'That connection no longer exists.', 'wp-divi-mcp' ) ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, whitelisted against $messages.
		$code = isset( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::NOTICE_ARG ] ) ) : '';
		if ( isset( $messages[ $code ] ) && ! ( 'created' === $code && ! $has_fresh ) ) {
			self::notice( $messages[ $code ][0], $messages[ $code ][1] );
		}
	}

	/**
	 * @return bool Whether the older companion plugin is active.
	 */
	private static function companion_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::COMPANION ) || ( is_multisite() && is_plugin_active_for_network( self::COMPANION ) );
	}

	/**
	 * @param string $type    success|warning|error.
	 * @param string $message Plain text.
	 * @return void
	 */
	private static function notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%s"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * The shown-once box.
	 *
	 * @param array $fresh {url, token, label}.
	 * @return void
	 */
	private static function render_new_connection( array $fresh ): void {
		$url   = (string) ( $fresh['url'] ?? '' );
		$label = (string) ( $fresh['label'] ?? '' );
		$slug  = sanitize_title( $label );
		if ( '' === $slug ) {
			$slug = 'wp-divi-mcp';
		}
		$command = 'claude mcp add --transport http ' . $slug . ' "' . $url . '"';

		echo '<div class="notice notice-success wpdm-new">';
		echo '<h2>' . esc_html__( 'Connection URL', 'wp-divi-mcp' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Copy it now. It will not be shown again.', 'wp-divi-mcp' ) . '</strong> ';
		echo esc_html__( 'Anyone with this URL can act as the chosen user, so treat it like a password.', 'wp-divi-mcp' ) . '</p>';

		echo '<p class="wpdm-copy"><input type="text" readonly class="large-text code" id="wpdm-url" value="' . esc_attr( $url ) . '" /> ';
		echo '<button type="button" class="button" data-wpdm-copy="wpdm-url">' . esc_html__( 'Copy URL', 'wp-divi-mcp' ) . '</button></p>';

		echo '<p>' . esc_html__( 'Claude Code:', 'wp-divi-mcp' ) . '</p>';
		echo '<p class="wpdm-copy"><input type="text" readonly class="large-text code" id="wpdm-cmd" value="' . esc_attr( $command ) . '" /> ';
		echo '<button type="button" class="button" data-wpdm-copy="wpdm-cmd">' . esc_html__( 'Copy command', 'wp-divi-mcp' ) . '</button></p>';
		echo '</div>';

		// Static script, no interpolated data. navigator.clipboard with a select-all fallback.
		echo '<script>
(function () {
	document.addEventListener("click", function (e) {
		var btn = e.target.closest ? e.target.closest("[data-wpdm-copy]") : null;
		if (!btn) { return; }
		var el = document.getElementById(btn.getAttribute("data-wpdm-copy"));
		if (!el) { return; }
		function fallback() {
			el.focus();
			el.select();
			try { document.execCommand("copy"); } catch (err) { /* selected; user can copy manually */ }
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(el.value).catch(fallback);
		} else {
			fallback();
		}
	});
}());
</script>';
	}

	/**
	 * @return void
	 */
	private static function render_connections(): void {
		$connections = Tokens::all();
		uasort(
			$connections,
			static function ( $a, $b ) {
				return (int) ( $b['created'] ?? 0 ) <=> (int) ( $a['created'] ?? 0 );
			}
		);

		echo '<h2>' . esc_html__( 'Connections', 'wp-divi-mcp' ) . '</h2>';
		if ( ! $connections ) {
			echo '<p>' . esc_html__( 'No connections yet.', 'wp-divi-mcp' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped wpdm-table"><thead><tr>';
		foreach ( array( 'Label', 'User', 'Mode', 'Created', 'Expires', 'Last used', 'Last IP', '' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $connections as $id => $c ) {
			$user    = get_userdata( (int) ( $c['user_id'] ?? 0 ) );
			$expires = isset( $c['expires'] ) && null !== $c['expires'] ? (int) $c['expires'] : null;
			$expired = null !== $expires && $expires <= time();

			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $c['label'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : __( '(deleted user)', 'wp-divi-mcp' ) ) . '</td>';
			echo '<td>' . esc_html( 'full' === ( $c['mode'] ?? '' ) ? __( 'Full access', 'wp-divi-mcp' ) : __( 'Read only', 'wp-divi-mcp' ) ) . '</td>';
			echo '<td>' . esc_html( self::format_time( isset( $c['created'] ) ? (int) $c['created'] : null ) ) . '</td>';
			echo '<td>' . esc_html( null === $expires ? __( 'Never', 'wp-divi-mcp' ) : self::format_time( $expires ) );
			if ( $expired ) {
				echo ' <strong>' . esc_html__( '(expired)', 'wp-divi-mcp' ) . '</strong>';
			}
			echo '</td>';
			echo '<td>' . esc_html( self::format_time( isset( $c['last_used'] ) ? (int) $c['last_used'] : null, __( 'Never', 'wp-divi-mcp' ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $c['last_ip'] ?? '' ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="wpdm_revoke" />';
			echo '<input type="hidden" name="connection_id" value="' . esc_attr( (string) $id ) . '" />';
			wp_nonce_field( 'wpdm_revoke' );
			echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'Revoke', 'wp-divi-mcp' ) . '</button>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @return void
	 */
	private static function render_new_form(): void {
		$users = get_users(
			array(
				'capability' => 'edit_posts',
				'orderby'    => 'display_name',
				'number'     => 200,
				'fields'     => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$me    = get_current_user_id();

		echo '<h2>' . esc_html__( 'New connection', 'wp-divi-mcp' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpdm_create" />';
		wp_nonce_field( 'wpdm_create' );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="wpdm-label">' . esc_html__( 'Label', 'wp-divi-mcp' ) . '</label></th>';
		echo '<td><input type="text" id="wpdm-label" name="label" class="regular-text" required maxlength="100" placeholder="' . esc_attr__( 'Claude Desktop', 'wp-divi-mcp' ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="wpdm-user">' . esc_html__( 'Acts as user', 'wp-divi-mcp' ) . '</label></th><td>';
		echo '<select id="wpdm-user" name="user_id">';
		foreach ( $users as $u ) {
			if ( ! self::can_mint_for( (int) $u->ID ) ) {
				continue;
			}
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $u->ID,
				selected( $me, (int) $u->ID, false ),
				esc_html( $u->display_name . ' (' . $u->user_login . ')' )
			);
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="wpdm-mode">' . esc_html__( 'Mode', 'wp-divi-mcp' ) . '</label></th><td>';
		echo '<select id="wpdm-mode" name="mode">';
		echo '<option value="read">' . esc_html__( 'Read only', 'wp-divi-mcp' ) . '</option>';
		echo '<option value="full">' . esc_html__( 'Full access', 'wp-divi-mcp' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="wpdm-expires">' . esc_html__( 'Expires', 'wp-divi-mcp' ) . '</label></th><td>';
		echo '<select id="wpdm-expires" name="expires">';
		echo '<option value="0">' . esc_html__( 'Never', 'wp-divi-mcp' ) . '</option>';
		echo '<option value="30">' . esc_html__( 'In 30 days', 'wp-divi-mcp' ) . '</option>';
		echo '<option value="90">' . esc_html__( 'In 90 days', 'wp-divi-mcp' ) . '</option>';
		echo '</select></td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Create connection', 'wp-divi-mcp' ) );
		echo '</form>';
	}

	/**
	 * @return void
	 */
	private static function render_endpoint_switch(): void {
		echo '<h2>' . esc_html__( 'Endpoint', 'wp-divi-mcp' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpdm_settings" />';
		wp_nonce_field( 'wpdm_settings' );
		echo '<p><label><input type="checkbox" name="wpdm_disabled" value="1"' . checked( (bool) get_option( 'wpdm_disabled' ), true, false ) . ' /> ';
		echo esc_html__( 'Disable endpoint (every connection gets 503 until re-enabled)', 'wp-divi-mcp' ) . '</label></p>';
		submit_button( __( 'Save', 'wp-divi-mcp' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * @return void
	 */
	private static function render_activity(): void {
		$rows = ActivityLog::recent( 200 );

		echo '<h2>' . esc_html__( 'Activity', 'wp-divi-mcp' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No tool calls logged yet. Entries are kept for 30 days.', 'wp-divi-mcp' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped wpdm-table"><thead><tr>';
		foreach ( array( 'Time', 'Connection', 'Tool', 'Target', 'OK', 'ms' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$ts = strtotime( (string) $row['ts'] . ' UTC' );
			echo '<tr>';
			echo '<td>' . esc_html( false === $ts ? (string) $row['ts'] : self::format_time( $ts ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['label'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $row['tool'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $row['target'] ) . '</td>';
			echo '<td>' . ( (int) $row['ok'] ? '<span class="wpdm-ok">' . esc_html__( 'OK', 'wp-divi-mcp' ) . '</span>' : '<span class="wpdm-fail">' . esc_html__( 'Failed', 'wp-divi-mcp' ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['ms'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param int|null $timestamp Unix timestamp.
	 * @param string   $fallback  Shown for null.
	 * @return string Site-local date and time.
	 */
	private static function format_time( ?int $timestamp, string $fallback = '' ): string {
		if ( null === $timestamp || $timestamp <= 0 ) {
			return $fallback;
		}
		return wp_date( 'Y-m-d H:i', $timestamp );
	}
}
