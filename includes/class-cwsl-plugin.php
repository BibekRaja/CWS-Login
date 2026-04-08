<?php
/**
 * Core routing, custom login URL, and login screen branding.
 *
 * Login URL rewriting and related redirects follow patterns common to custom login URL
 * plugins. Reference / prior art: WPS Hide Login (GPL-2.0+).
 *
 * @package CWS_Login
 * @link   https://wordpress.org/plugins/wps-hide-login/ WPS Hide Login (reference)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton handling request rewriting, redirects, and login branding hooks.
 */
final class CWSL_Plugin {

	public const OPTION_LOGIN_SLUG    = 'cwsl_login_slug';
	public const OPTION_REDIRECT_SLUG = 'cwsl_redirect_slug';
	public const OPTION_LOGO_ID       = 'cwsl_login_logo_attachment_id';

	/**
	 * When true, the real wp-login.php was requested and should be masked.
	 *
	 * @var bool
	 */
	private $cwsl_mask_wp_login = false;

	/**
	 * False when another login-URL plugin is active or WordPress is too old.
	 *
	 * @var bool
	 */
	private $cwsl_ready = true;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return bool Whether login masking and related hooks are active.
	 */
	public function cwsl_is_ready() {
		return $this->cwsl_ready;
	}

	private function __construct() {
		global $wp_version;

		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			$this->cwsl_ready = false;
			add_action( 'admin_notices', array( $this, 'cwsl_notice_wp_version' ) );
			return;
		}

		if ( $this->cwsl_conflicting_plugin_active() ) {
			$this->cwsl_ready = false;
			add_action( 'admin_notices', array( $this, 'cwsl_notice_conflict' ) );
			return;
		}

		if ( is_multisite() && ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_multisite() && is_plugin_active_for_network( CWSL_PLUGIN_BASENAME ) ) {
			add_action( 'wpmu_options', array( $this, 'cwsl_network_options_form' ) );
			add_action( 'update_wpmu_options', array( $this, 'cwsl_network_options_save' ) );
			add_filter( 'network_admin_plugin_action_links_' . CWSL_PLUGIN_BASENAME, array( $this, 'cwsl_plugin_action_links' ) );
		}

		if ( is_multisite() ) {
			add_action( 'wp_before_admin_bar_render', array( $this, 'cwsl_admin_bar_my_sites_links' ), 999 );
		}

		add_action( 'admin_init', array( $this, 'cwsl_maybe_redirect_old_settings' ) );
		add_action( 'plugins_loaded', array( $this, 'cwsl_plugins_loaded_routing' ), 9999 );
		add_action( 'wp_loaded', array( $this, 'cwsl_wp_loaded' ) );
		add_action( 'setup_theme', array( $this, 'cwsl_block_customizer_for_guests' ), 1 );
		add_action( 'init', array( $this, 'cwsl_block_signup_activate_on_single_site' ) );
		add_action( 'template_redirect', array( $this, 'cwsl_privacy_export_confirm_redirect' ) );

		add_filter( 'site_url', array( $this, 'cwsl_filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'cwsl_filter_network_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'cwsl_filter_wp_redirect' ), 10, 2 );
		add_filter( 'login_url', array( $this, 'cwsl_filter_login_url' ), 10, 3 );
		add_filter( 'site_option_welcome_email', array( $this, 'cwsl_filter_welcome_email' ) );
		add_filter( 'user_request_action_email_content', array( $this, 'cwsl_filter_export_email_confirm_url' ), 999, 2 );

		add_filter( 'plugin_action_links_' . CWSL_PLUGIN_BASENAME, array( $this, 'cwsl_plugin_action_links' ) );
		add_filter( 'manage_sites_action_links', array( $this, 'cwsl_multisite_site_row_login_link' ), 10, 3 );

		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		add_action( 'login_head', array( $this, 'cwsl_login_logo_assets' ), 99 );
		add_filter( 'login_headerurl', array( $this, 'cwsl_login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'cwsl_login_header_text' ) );
	}

	/**
	 * Activation hook.
	 */
	public static function on_activation() {
		if ( ! get_option( self::OPTION_LOGIN_SLUG ) ) {
			add_option( self::OPTION_LOGIN_SLUG, 'login' );
		}
		if ( ! get_option( self::OPTION_REDIRECT_SLUG ) ) {
			add_option( self::OPTION_REDIRECT_SLUG, '404' );
		}
		do_action( 'cwsl_activate' );
	}

	/**
	 * @return bool
	 */
	private function cwsl_conflicting_plugin_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		/**
		 * Other plugins (WordPress plugin basename, e.g. `folder/file.php`) that change the login URL
		 * and must not run alongside CWS Login. Add basenames via this filter if needed.
		 *
		 * @param string[] $basenames Plugin basenames relative to wp-content/plugins.
		 */
		$other_login_plugins = apply_filters(
			'cwsl_conflicting_plugin_basenames',
			array(
				'rename-wp-login/rename-wp-login.php',
			)
		);
		if ( ! is_array( $other_login_plugins ) ) {
			return false;
		}
		foreach ( $other_login_plugins as $basename ) {
			if ( ! is_string( $basename ) || '' === $basename ) {
				continue;
			}
			$active = is_plugin_active( $basename )
				|| ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $basename ) );
			if ( $active ) {
				return true;
			}
		}
		return false;
	}

	public function cwsl_notice_wp_version() {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'CWS Login requires WordPress 5.8 or newer.', 'cws-login' ) . '</p></div>';
	}

	public function cwsl_notice_conflict() {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'CWS Login is not controlling the login URL because another plugin is already active that changes it. Deactivate that plugin first if you want to use CWS Login.', 'cws-login' ) . '</p></div>';
	}

	/**
	 * @param int|string $blog_id Optional multisite blog id.
	 * @return string
	 */
	public function cwsl_get_login_slug( $blog_id = '' ) {
		if ( $blog_id ) {
			$slug = get_blog_option( (int) $blog_id, self::OPTION_LOGIN_SLUG );
			if ( $slug ) {
				return $slug;
			}
			if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( CWSL_PLUGIN_BASENAME ) ) {
				return (string) get_site_option( self::OPTION_LOGIN_SLUG, 'login' );
			}
			return 'login';
		}

		$slug = get_option( self::OPTION_LOGIN_SLUG );
		if ( $slug ) {
			return $slug;
		}
		if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( CWSL_PLUGIN_BASENAME ) ) {
			return (string) get_site_option( self::OPTION_LOGIN_SLUG, 'login' );
		}
		return 'login';
	}

	/**
	 * @return string
	 */
	public function cwsl_get_redirect_slug() {
		$slug = get_option( self::OPTION_REDIRECT_SLUG );
		if ( $slug ) {
			return $slug;
		}
		if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( CWSL_PLUGIN_BASENAME ) ) {
			return (string) get_site_option( self::OPTION_REDIRECT_SLUG, '404' );
		}
		return '404';
	}

	public function cwsl_trailing_slash_preference() {
		$structure = (string) get_option( 'permalink_structure' );
		return ( '/' === substr( $structure, -1 ) );
	}

	/**
	 * @param string $path Path segment or full relative path.
	 * @return string
	 */
	private function cwsl_apply_slash_style( $path ) {
		return $this->cwsl_trailing_slash_preference()
			? trailingslashit( $path )
			: untrailingslashit( $path );
	}

	/**
	 * @param string|null $scheme URL scheme.
	 * @return string
	 */
	public function cwsl_build_login_url( $scheme = null ) {
		$base = apply_filters( 'cwsl_login_home_url', home_url( '/', $scheme ) );

		if ( get_option( 'permalink_structure' ) ) {
			return $this->cwsl_apply_slash_style( $base . $this->cwsl_get_login_slug() );
		}

		return $base . '?' . $this->cwsl_get_login_slug();
	}

	/**
	 * @param string|null $scheme URL scheme.
	 * @return string
	 */
	public function cwsl_build_block_redirect_url( $scheme = null ) {
		if ( get_option( 'permalink_structure' ) ) {
			return $this->cwsl_apply_slash_style( home_url( '/', $scheme ) . $this->cwsl_get_redirect_slug() );
		}
		return home_url( '/', $scheme ) . '?' . $this->cwsl_get_redirect_slug();
	}

	private function cwsl_load_front_controller() {
		global $pagenow;

		$pagenow = 'index.php';

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant required by template loader.
			define( 'WP_USE_THEMES', true );
		}

		wp();
		require_once ABSPATH . WPINC . '/template-loader.php';
		exit;
	}

	/**
	 * @return string[]
	 */
	private function cwsl_reserved_slugs() {
		$wp = new WP();
		return array_merge( $wp->public_query_vars, $wp->private_query_vars );
	}

	/**
	 * @param string $slug Candidate slug.
	 * @return bool
	 */
	public function cwsl_is_slug_allowed( $slug ) {
		$slug = sanitize_title_with_dashes( $slug );
		if ( '' === $slug || false !== strpos( $slug, 'wp-login' ) ) {
			return false;
		}
		return ! in_array( $slug, $this->cwsl_reserved_slugs(), true );
	}

	/**
	 * Raw request URI for routing (path and query). Used for parsing only, not printed.
	 *
	 * @return string
	 */
	private function cwsl_get_request_uri() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server variable unslashed for parse_url; never output.
		return rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	public function cwsl_plugins_loaded_routing() {
		global $pagenow;

		$request_uri = $this->cwsl_get_request_uri();
		$parsed      = wp_parse_url( $request_uri );

		$hits_wp_login_file = ( false !== strpos( $request_uri, 'wp-login.php' ) )
			|| ( isset( $parsed['path'] ) && untrailingslashit( $parsed['path'] ) === site_url( 'wp-login', 'relative' ) );

		if ( $hits_wp_login_file && ! is_admin() ) {
			$this->cwsl_mask_wp_login = true;
			$_SERVER['REQUEST_URI']    = $this->cwsl_apply_slash_style( '/' . str_repeat( '-/', 10 ) );
			$pagenow                   = 'index.php';
			return;
		}

		$slug_match_path = isset( $parsed['path'] )
			&& untrailingslashit( $parsed['path'] ) === home_url( $this->cwsl_get_login_slug(), 'relative' );

		$login_slug_qs = $this->cwsl_get_login_slug();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public request for custom login slug (plain permalinks); not an admin form.
		$slug_match_query = ! get_option( 'permalink_structure' )
			&& isset( $_GET[ $login_slug_qs ] )
			&& ( '' === $_GET[ $login_slug_qs ] || null === $_GET[ $login_slug_qs ] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $slug_match_path || $slug_match_query ) {
			$_SERVER['SCRIPT_NAME'] = $this->cwsl_get_login_slug();
			$pagenow                = 'wp-login.php';
			return;
		}

		$hits_register = ( false !== strpos( $request_uri, 'wp-register.php' ) )
			|| ( isset( $parsed['path'] ) && untrailingslashit( $parsed['path'] ) === site_url( 'wp-register', 'relative' ) );

		if ( $hits_register && ! is_admin() ) {
			$this->cwsl_mask_wp_login = true;
			$_SERVER['REQUEST_URI']    = $this->cwsl_apply_slash_style( '/' . str_repeat( '-/', 10 ) );
			$pagenow                   = 'index.php';
		}
	}

	public function cwsl_wp_loaded() {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification -- Front-end login / admin gate; mirrors wp-login.php query usage (no admin nonce).
		$request_uri = $this->cwsl_get_request_uri();
		$parsed      = wp_parse_url( $request_uri );

		do_action( 'cwsl_before_redirect', $parsed );

		$is_post_password = isset( $_GET['action'], $_POST['post_password'] ) && 'postpass' === $_GET['action'];
		if ( $is_post_password ) {
			return;
		}

		if (
			is_admin()
			&& ! is_user_logged_in()
			&& ! defined( 'WP_CLI' )
			&& ! defined( 'DOING_AJAX' )
			&& ! defined( 'DOING_CRON' )
			&& 'admin-post.php' !== $pagenow
			&& ( ! isset( $parsed['path'] ) || '/wp-admin/options.php' !== $parsed['path'] )
		) {
			wp_safe_redirect( $this->cwsl_build_block_redirect_url() );
			exit;
		}

		if ( ! is_user_logged_in() && isset( $_GET['wc-ajax'] ) && 'profile.php' === $pagenow ) {
			wp_safe_redirect( $this->cwsl_build_block_redirect_url() );
			exit;
		}

		if ( ! is_user_logged_in() && isset( $parsed['path'] ) && '/wp-admin/options.php' === $parsed['path'] ) {
			wp_safe_redirect( $this->cwsl_build_block_redirect_url() );
			exit;
		}

		if (
			'wp-login.php' === $pagenow
			&& isset( $parsed['path'] )
			&& $parsed['path'] !== $this->cwsl_apply_slash_style( $parsed['path'] )
			&& get_option( 'permalink_structure' )
		) {
			$query = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
			wp_safe_redirect( $this->cwsl_apply_slash_style( $this->cwsl_build_login_url() ) . $query );
			exit;
		}

		if ( $this->cwsl_mask_wp_login ) {
			$referer = wp_get_referer();
			if ( $referer && false !== strpos( $referer, 'wp-activate.php' ) ) {
				$parts = wp_parse_url( $referer );
				if ( ! empty( $parts['query'] ) ) {
					parse_str( $parts['query'], $q );
					require_once ABSPATH . WPINC . '/ms-functions.php';
					if ( ! empty( $q['key'] ) ) {
						$result = wpmu_activate_signup( $q['key'] );
						if ( is_wp_error( $result ) ) {
							$code = $result->get_error_code();
							if ( 'already_active' === $code || 'blog_taken' === $code ) {
								$query = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
								wp_safe_redirect( $this->cwsl_build_login_url() . $query );
								exit;
							}
						}
					}
				}
			}
			$this->cwsl_load_front_controller();
		}

		if ( 'wp-login.php' === $pagenow ) {
			/*
			 * wp-login.php expects these globals (see wp-login.php, login_header, login form).
			 * When the file is loaded via require from here—not as the front controller—$user_login
			 * may never be set (no POST), and $error is read for autofocus; initialize globals before require.
			 */
			global $error, $interim_login, $action, $user_login;

			if ( ! isset( $error ) ) {
				$error = '';
			}
			if ( ! isset( $user_login ) ) {
				$user_login = '';
			}

			$redirect_to           = admin_url();
			$requested_redirect_to = '';
			if ( isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) {
				$requested_redirect_to = wp_validate_redirect(
					sanitize_text_field( wp_unslash( $_REQUEST['redirect_to'] ) ),
					''
				);
			}

			if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
				$user               = wp_get_current_user();
				$logged_in_redirect = apply_filters( 'cwsl_logged_in_redirect', $redirect_to, $requested_redirect_to, $user );
				wp_safe_redirect( $logged_in_redirect );
				exit;
			}

			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	public function cwsl_block_customizer_for_guests() {
		global $pagenow;
		if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
			wp_die( esc_html__( 'This has been disabled.', 'cws-login' ), '', array( 'response' => 403 ) );
		}
	}

	public function cwsl_block_signup_activate_on_single_site() {
		if ( is_multisite() ) {
			return;
		}
		$uri = $this->cwsl_get_request_uri();
		if (
			( false !== strpos( $uri, 'wp-signup' ) || false !== strpos( $uri, 'wp-activate' ) )
			&& false === apply_filters( 'cwsl_allow_signup_urls', false )
		) {
			wp_die( esc_html__( 'This feature is not enabled.', 'cws-login' ) );
		}
	}

	public function cwsl_privacy_export_confirm_redirect() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Core privacy export link; confirm_key validated below.
		if (
			empty( $_GET['action'] )
			|| 'confirmaction' !== $_GET['action']
			|| empty( $_GET['request_id'] )
			|| empty( $_GET['confirm_key'] )
		) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		$request_id = (int) $_GET['request_id'];
		$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
		$result     = wp_validate_user_request_key( $request_id, $key );
		if ( is_wp_error( $result ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'action'      => 'confirmaction',
					'request_id'  => $request_id,
					'confirm_key' => $key,
				),
				$this->cwsl_build_login_url()
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		exit;
	}

	public function cwsl_filter_site_url( $url, $path, $scheme, $blog_id ) {
		return $this->cwsl_rewrite_wp_login_in_url( $url, $scheme );
	}

	public function cwsl_filter_network_site_url( $url, $path, $scheme ) {
		return $this->cwsl_rewrite_wp_login_in_url( $url, $scheme );
	}

	public function cwsl_filter_wp_redirect( $location, $status ) {
		if ( false !== strpos( $location, 'https://wordpress.com/wp-login.php' ) ) {
			return $location;
		}
		return $this->cwsl_rewrite_wp_login_in_url( $location );
	}

	/**
	 * @param string      $url    Full URL.
	 * @param string|null $scheme Optional scheme.
	 * @return string
	 */
	private function cwsl_rewrite_wp_login_in_url( $url, $scheme = null ) {
		global $pagenow;

		$original = $url;

		if ( false !== strpos( $url, 'wp-login.php?action=postpass' ) ) {
			return $url;
		}

		if ( is_multisite() && 'install.php' === $pagenow ) {
			return $url;
		}

		if ( false !== strpos( $url, 'wp-login.php' ) && false === strpos( (string) wp_get_referer(), 'wp-login.php' ) ) {
			$use_scheme = is_ssl() ? 'https' : $scheme;
			$parts      = explode( '?', $url, 2 );
			if ( isset( $parts[1] ) ) {
				parse_str( $parts[1], $args );
				if ( isset( $args['login'] ) ) {
					$args['login'] = rawurlencode( $args['login'] );
				}
				$url = add_query_arg( $args, $this->cwsl_build_login_url( $use_scheme ) );
			} else {
				$url = $this->cwsl_build_login_url( $use_scheme );
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Login URL filter; post_password / GF query match core patterns without admin nonce.
		if ( isset( $_POST['post_password'] ) ) {
			global $current_user;
			if ( ! is_user_logged_in() && is_wp_error( wp_authenticate_username_password( null, $current_user->user_login, sanitize_text_field( wp_unslash( $_POST['post_password'] ) ) ) ) ) {
				return $original;
			}
		}

		if ( ! is_user_logged_in() && file_exists( WP_CONTENT_DIR . '/plugins/gravityforms/gravityforms.php' ) && isset( $_GET['gf_page'] ) ) {
			return $original;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return $url;
	}

	public function cwsl_filter_login_url( $login_url, $redirect, $force_reauth ) {
		if ( is_404() ) {
			return '#';
		}
		if ( false === $force_reauth ) {
			return $login_url;
		}
		if ( empty( $redirect ) ) {
			return $login_url;
		}
		$parts = explode( '?', $redirect, 2 );
		if ( isset( $parts[0] ) && admin_url( 'options.php' ) === $parts[0] ) {
			return admin_url();
		}
		return $login_url;
	}

	public function cwsl_filter_welcome_email( $value ) {
		$slug = get_site_option( self::OPTION_LOGIN_SLUG, 'login' );
		return str_replace( 'wp-login.php', trailingslashit( $slug ), $value );
	}

	/**
	 * Aligns privacy export confirmation links with core email placeholder behavior.
	 *
	 * @param string               $email_text Email body.
	 * @param array<string, mixed> $email_data Data.
	 * @return string
	 */
	public function cwsl_filter_export_email_confirm_url( $email_text, $email_data ) {
		if ( empty( $email_data['confirm_url'] ) ) {
			return $email_text;
		}
		$fixed = str_replace( $this->cwsl_get_login_slug() . '/', 'wp-login.php', $email_data['confirm_url'] );
		return str_replace( '###CONFIRM_URL###', esc_url_raw( $fixed ), $email_text );
	}

	public function cwsl_network_options_form() {
		$login_slug    = get_site_option( self::OPTION_LOGIN_SLUG, 'login' );
		$redirect_slug = get_site_option( self::OPTION_REDIRECT_SLUG, '404' );
		echo '<h2 id="cwsl-network-settings">' . esc_html__( 'CWS Login (network defaults)', 'cws-login' ) . '</h2>';
		echo '<p>' . esc_html__( 'These defaults apply when a site has not set its own slugs. Individual sites can override them from the CWS Login screen.', 'cws-login' ) . '</p>';
		echo '<table class="form-table"><tr>';
		echo '<th scope="row"><label for="cwsl_network_login_slug">' . esc_html__( 'Login path (default)', 'cws-login' ) . '</label></th>';
		echo '<td><input name="cwsl_network_login_slug" id="cwsl_network_login_slug" type="text" value="' . esc_attr( $login_slug ) . '" class="regular-text" /></td>';
		echo '</tr><tr>';
		echo '<th scope="row"><label for="cwsl_network_redirect_slug">' . esc_html__( 'Redirect path (default)', 'cws-login' ) . '</label></th>';
		echo '<td><input name="cwsl_network_redirect_slug" id="cwsl_network_redirect_slug" type="text" value="' . esc_attr( $redirect_slug ) . '" class="regular-text" /></td>';
		echo '</tr></table>';
	}

	public function cwsl_network_options_save() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		if ( empty( $_POST ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'siteoptions' ) ) {
			return;
		}
		if ( isset( $_POST['cwsl_network_login_slug'] ) ) {
			$slug = sanitize_title_with_dashes( wp_unslash( $_POST['cwsl_network_login_slug'] ) );
			if ( $slug && $this->cwsl_is_slug_allowed( $slug ) ) {
				update_site_option( self::OPTION_LOGIN_SLUG, $slug );
				flush_rewrite_rules( true );
			}
		}
		if ( isset( $_POST['cwsl_network_redirect_slug'] ) ) {
			$redir = sanitize_title_with_dashes( wp_unslash( $_POST['cwsl_network_redirect_slug'] ) );
			if ( $redir && $this->cwsl_is_slug_allowed( $redir ) ) {
				update_site_option( self::OPTION_REDIRECT_SLUG, $redir );
				flush_rewrite_rules( true );
			}
		}
	}

	/**
	 * @param string[] $links Plugin action links.
	 * @return string[]
	 */
	public function cwsl_plugin_action_links( $links ) {
		$url = is_network_admin()
			? network_admin_url( 'settings.php#cwsl-network-settings' )
			: admin_url( 'admin.php?page=cwsl-login' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cws-login' ) . '</a>' );
		return $links;
	}

	public function cwsl_admin_bar_my_sites_links() {
		global $wp_admin_bar;
		$nodes = $wp_admin_bar->get_nodes();
		if ( ! $nodes ) {
			return;
		}
		foreach ( $nodes as $node ) {
			if ( preg_match( '/^blog-(\d+)(.*)/', $node->id, $matches ) ) {
				$blog_id   = $matches[1];
				$login_slug = $this->cwsl_get_login_slug( $blog_id );
				if ( ! $login_slug ) {
					continue;
				}
				if ( ! $matches[2] || '-d' === $matches[2] ) {
					$args       = $node;
					$old_href   = $args->href;
					$args->href = preg_replace( '/wp-admin\/$/', $login_slug . '/', $old_href );
					if ( $old_href !== $args->href ) {
						$wp_admin_bar->add_node( $args );
					}
				} elseif ( isset( $node->href ) && false !== strpos( $node->href, '/wp-admin/' ) ) {
					$wp_admin_bar->remove_node( $node->id );
				}
			}
		}
	}

	/**
	 * @param string[] $actions Actions.
	 * @param int      $blog_id Site ID.
	 * @param string   $blogname Site name (unused).
	 * @return string[]
	 */
	public function cwsl_multisite_site_row_login_link( $actions, $blog_id, $blogname ) {
		$actions['cwsl-login'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( get_site_url( (int) $blog_id, $this->cwsl_get_login_slug( $blog_id ) ) ),
			esc_html__( 'Login URL', 'cws-login' )
		);
		return $actions;
	}

	public function cwsl_maybe_redirect_old_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Legacy redirect only; no state change.
		if ( empty( $_GET['page'] ) || 'cwsl_settings_legacy' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=cwsl-login' ) );
		exit;
	}

	public function cwsl_login_logo_assets() {
		$attachment_id = (int) get_option( self::OPTION_LOGO_ID );
		if ( $attachment_id < 1 || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url ) {
			return;
		}
		echo '<style id="cwsl-login-logo-css">';
		echo '#login h1 a, #login h1 .wp-login-logo { background-image: url(' . esc_url( $url ) . ') !important; background-size: contain; background-position: center center; width: auto; max-width: 320px; height: 84px; }';
		echo '</style>';
	}

	/**
	 * @param string $url Default header link.
	 * @return string
	 */
	public function cwsl_login_header_url( $url ) {
		$logo_id = (int) get_option( self::OPTION_LOGO_ID );
		if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
			return home_url( '/' );
		}
		return $url;
	}

	/**
	 * @param string $title Default title/alt.
	 * @return string
	 */
	public function cwsl_login_header_text( $title ) {
		$logo_id = (int) get_option( self::OPTION_LOGO_ID );
		if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
			return get_bloginfo( 'name', 'display' );
		}
		return $title;
	}
}
