<?php
/**
 * Plugin Name:       CWS Login
 * Plugin URI:        https://wordpress.org/plugins/cws-login
 * Description:       Replaces the default WordPress login URL with a custom slug, blocks direct access to wp-login.php for visitors, redirects anonymous users away from wp-admin to a configurable destination, and lets you brand the login screen with your company logo.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Bibek Raja
 * Author URI:        https://profiles.wordpress.org/bibekraja/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cws-login
 * Domain Path:       /languages
 *
 * Copyright (C) Bibek Raja and contributors. CWS Login is free software; you can
 * redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * Acknowledgment — login URL redirection: The general idea of using a custom path
 * for the login screen, masking direct access to wp-login.php for visitors, and
 * redirecting anonymous wp-admin requests is a well-known pattern in the WordPress
 * ecosystem. WPS Hide Login (GPL-2.0+, https://wordpress.org/plugins/wps-hide-login/)
 * is credited here as prior art / reference for that category of behavior.
 *
 * @package CWS_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CWSL_VERSION', '1.0.1' );
define( 'CWSL_PLUGIN_FILE', __FILE__ );
define( 'CWSL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CWSL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CWSL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CWSL_PLUGIN_DIR . 'includes/class-cwsl-plugin.php';
require_once CWSL_PLUGIN_DIR . 'includes/class-cwsl-admin.php';

register_activation_hook( __FILE__, array( 'CWSL_Plugin', 'on_activation' ) );

add_action(
	'plugins_loaded',
	function () {
		$core = CWSL_Plugin::instance();
		if ( $core->cwsl_is_ready() ) {
			CWSL_Admin::instance();
		}
	},
	5
);
