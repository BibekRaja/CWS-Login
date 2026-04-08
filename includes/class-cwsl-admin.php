<?php
/**
 * Dashboard settings UI for CWS Login.
 *
 * @package CWS_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page under a top-level admin menu.
 */
final class CWSL_Admin {

	/**
	 * Menu slug.
	 */
	private const MENU_SLUG = 'cwsl-login';

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

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'cwsl_register_menu' ) );
		add_action( 'admin_init', array( $this, 'cwsl_register_settings' ) );
		add_action( 'admin_init', array( $this, 'cwsl_register_privacy_suggestion' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'cwsl_enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'cwsl_settings_saved_notice' ) );
	}

	/**
	 * Suggested text for the site privacy policy (WordPress Privacy API).
	 */
	public function cwsl_register_privacy_suggestion() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'This site may use a custom login page address and an optional logo image on the login screen. Login path settings and the logo attachment ID are stored in the WordPress database. No personal data is sent to third parties by this feature.', 'cws-login' ) . '</p>';
		wp_add_privacy_policy_content( __( 'CWS Login', 'cws-login' ), wp_kses_post( $content ) );
	}

	public function cwsl_register_menu() {
		add_menu_page(
			__( 'CWS Login', 'cws-login' ),
			__( 'CWS Login', 'cws-login' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'cwsl_render_page' ),
			'dashicons-lock',
			80
		);
	}

	public function cwsl_register_settings() {
		$common = array(
			'show_in_rest' => false,
			'capability'   => 'manage_options',
		);
		register_setting(
			'cwsl_settings_group',
			CWSL_Plugin::OPTION_LOGIN_SLUG,
			array_merge(
				$common,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'cwsl_sanitize_login_slug' ),
					'default'           => 'login',
				)
			)
		);
		register_setting(
			'cwsl_settings_group',
			CWSL_Plugin::OPTION_REDIRECT_SLUG,
			array_merge(
				$common,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'cwsl_sanitize_redirect_slug' ),
					'default'           => '404',
				)
			)
		);
		register_setting(
			'cwsl_settings_group',
			CWSL_Plugin::OPTION_LOGO_ID,
			array_merge(
				$common,
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'cwsl_sanitize_logo_id' ),
					'default'           => 0,
				)
			)
		);
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function cwsl_sanitize_login_slug( $value ) {
		$slug = sanitize_title_with_dashes( is_string( $value ) ? $value : '' );
		$core = CWSL_Plugin::instance();
		if ( ! $core->cwsl_is_slug_allowed( $slug ) ) {
			add_settings_error(
				CWSL_Plugin::OPTION_LOGIN_SLUG,
				'cwsl-invalid-login-slug',
				__( 'That login path is not allowed. Choose a different slug (letters, numbers, hyphens; cannot match WordPress reserved paths or contain “wp-login”).', 'cws-login' )
			);
			return (string) get_option( CWSL_Plugin::OPTION_LOGIN_SLUG, 'login' );
		}
		if ( isset( $_POST[ CWSL_Plugin::OPTION_REDIRECT_SLUG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings form nonce.
			$other = sanitize_title_with_dashes( wp_unslash( $_POST[ CWSL_Plugin::OPTION_REDIRECT_SLUG ] ) );
			if ( $slug === $other ) {
				add_settings_error(
					CWSL_Plugin::OPTION_LOGIN_SLUG,
					'cwsl-login-redirect-same',
					__( 'The login path and redirect path must be different.', 'cws-login' )
				);
				return (string) get_option( CWSL_Plugin::OPTION_LOGIN_SLUG, 'login' );
			}
		}
		return $slug;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function cwsl_sanitize_redirect_slug( $value ) {
		$slug = sanitize_title_with_dashes( is_string( $value ) ? $value : '' );
		$core = CWSL_Plugin::instance();
		if ( '' === $slug || ! $core->cwsl_is_slug_allowed( $slug ) ) {
			add_settings_error(
				CWSL_Plugin::OPTION_REDIRECT_SLUG,
				'cwsl-invalid-redirect-slug',
				__( 'That redirect path is not valid or conflicts with a reserved WordPress path.', 'cws-login' )
			);
			return (string) get_option( CWSL_Plugin::OPTION_REDIRECT_SLUG, '404' );
		}
		if ( isset( $_POST[ CWSL_Plugin::OPTION_LOGIN_SLUG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings form nonce.
			$other = sanitize_title_with_dashes( wp_unslash( $_POST[ CWSL_Plugin::OPTION_LOGIN_SLUG ] ) );
			if ( $slug === $other ) {
				add_settings_error(
					CWSL_Plugin::OPTION_REDIRECT_SLUG,
					'cwsl-redirect-login-same',
					__( 'The login path and redirect path must be different.', 'cws-login' )
				);
				return (string) get_option( CWSL_Plugin::OPTION_REDIRECT_SLUG, '404' );
			}
		}
		return $slug;
	}

	/**
	 * @param mixed $value Attachment ID.
	 * @return int
	 */
	public function cwsl_sanitize_logo_id( $value ) {
		$id = absint( $value );
		if ( $id < 1 ) {
			return 0;
		}
		if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			add_settings_error(
				CWSL_Plugin::OPTION_LOGO_ID,
				'cwsl-invalid-logo',
				__( 'Please choose a valid image attachment for the login logo.', 'cws-login' )
			);
			return (int) get_option( CWSL_Plugin::OPTION_LOGO_ID, 0 );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			add_settings_error(
				CWSL_Plugin::OPTION_LOGO_ID,
				'cwsl-logo-capability',
				__( 'You do not have permission to use that file as the login logo.', 'cws-login' )
			);
			return (int) get_option( CWSL_Plugin::OPTION_LOGO_ID, 0 );
		}
		return $id;
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public function cwsl_enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'cwsl-admin',
			CWSL_PLUGIN_URL . 'assets/cwsl-admin.js',
			array( 'jquery' ),
			CWSL_VERSION,
			true
		);
		wp_localize_script(
			'cwsl-admin',
			'cwslAdmin',
			array(
				'chooseLogo' => __( 'Choose company logo', 'cws-login' ),
				'useLogo'    => __( 'Use this image', 'cws-login' ),
			)
		);
		wp_enqueue_style(
			'cwsl-admin',
			CWSL_PLUGIN_URL . 'assets/cwsl-admin.css',
			array(),
			CWSL_VERSION
		);
	}

	public function cwsl_settings_saved_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin notice display.
		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! isset( $_GET['settings-updated'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$core = CWSL_Plugin::instance();
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'Settings saved.', 'cws-login' );
		echo ' ';
		printf(
			/* translators: %s: login URL */
			esc_html__( 'Your login page is now: %s', 'cws-login' ),
			'<strong><a href="' . esc_url( $core->cwsl_build_login_url() ) . '">' . esc_html( $core->cwsl_build_login_url() ) . '</a></strong>'
		);
		echo '</p></div>';
	}

	public function cwsl_render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$core        = CWSL_Plugin::instance();
		$login_slug  = (string) get_option( CWSL_Plugin::OPTION_LOGIN_SLUG, 'login' );
		$redirect    = (string) get_option( CWSL_Plugin::OPTION_REDIRECT_SLUG, '404' );
		$logo_id     = (int) get_option( CWSL_Plugin::OPTION_LOGO_ID, 0 );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$home        = trailingslashit( home_url() );
		$pretty      = (bool) get_option( 'permalink_structure' );

		settings_errors();
		?>
		<div class="wrap cwsl-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description cwsl-intro">
				<?php esc_html_e( 'Change the public login address, control where anonymous visitors are sent if they try wp-admin or the old login URL, and upload a logo for the login screen.', 'cws-login' ); ?>
			</p>

			<form method="post" action="options.php" class="cwsl-form">
				<?php
				settings_fields( 'cwsl_settings_group' );
				?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="cwsl_login_slug"><?php esc_html_e( 'Login path', 'cws-login' ); ?></label>
							</th>
							<td>
								<?php if ( $pretty ) : ?>
									<code class="cwsl-url-prefix"><?php echo esc_html( $home ); ?></code>
									<input name="<?php echo esc_attr( CWSL_Plugin::OPTION_LOGIN_SLUG ); ?>" id="cwsl_login_slug" type="text" class="regular-text" value="<?php echo esc_attr( $login_slug ); ?>" autocomplete="off" />
									<code><?php echo $core->cwsl_trailing_slash_preference() ? ' /' : ''; ?></code>
								<?php else : ?>
									<code class="cwsl-url-prefix"><?php echo esc_html( $home ); ?>?</code>
									<input name="<?php echo esc_attr( CWSL_Plugin::OPTION_LOGIN_SLUG ); ?>" id="cwsl_login_slug" type="text" class="regular-text" value="<?php echo esc_attr( $login_slug ); ?>" autocomplete="off" />
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Visitors will sign in at this address. The standard wp-login.php URL is not used for the login screen for visitors.', 'cws-login' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cwsl_redirect_slug"><?php esc_html_e( 'Redirect path (blocked access)', 'cws-login' ); ?></label>
							</th>
							<td>
								<?php if ( $pretty ) : ?>
									<code class="cwsl-url-prefix"><?php echo esc_html( $home ); ?></code>
									<input name="<?php echo esc_attr( CWSL_Plugin::OPTION_REDIRECT_SLUG ); ?>" id="cwsl_redirect_slug" type="text" class="regular-text" value="<?php echo esc_attr( $redirect ); ?>" autocomplete="off" />
									<code><?php echo $core->cwsl_trailing_slash_preference() ? ' /' : ''; ?></code>
								<?php else : ?>
									<code class="cwsl-url-prefix"><?php echo esc_html( $home ); ?>?</code>
									<input name="<?php echo esc_attr( CWSL_Plugin::OPTION_REDIRECT_SLUG ); ?>" id="cwsl_redirect_slug" type="text" class="regular-text" value="<?php echo esc_attr( $redirect ); ?>" autocomplete="off" />
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'If someone who is not logged in tries wp-admin, the old login URL, or related entry points, they are sent here—commonly a 404 page.', 'cws-login' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Login page logo', 'cws-login' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CWSL_Plugin::OPTION_LOGO_ID ); ?>" id="cwsl_logo_attachment_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
								<div id="cwsl-logo-preview" class="cwsl-logo-preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
									<?php if ( $logo_url ) : ?>
										<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
									<?php endif; ?>
								</div>
								<p>
									<button type="button" class="button" id="cwsl-select-logo"><?php esc_html_e( 'Select image', 'cws-login' ); ?></button>
									<button type="button" class="button" id="cwsl-remove-logo" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'cws-login' ); ?></button>
								</p>
								<p class="description">
									<?php esc_html_e( 'Replaces the WordPress logo on the login screen with your company logo.', 'cws-login' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save changes', 'cws-login' ) ); ?>
			</form>
		</div>
		<?php
	}
}
