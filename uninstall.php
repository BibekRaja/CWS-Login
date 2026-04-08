<?php
/**
 * Uninstall CWS Login — removes stored options.
 *
 * @package CWS_Login
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

if ( is_multisite() ) {
	delete_site_option( 'cwsl_login_slug' );
	delete_site_option( 'cwsl_redirect_slug' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must enumerate all blogs; no object cache layer applies.
	$cwsl_blog_rows = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	if ( $cwsl_blog_rows ) {
		foreach ( $cwsl_blog_rows as $cwsl_blog_row ) {
			switch_to_blog( (int) $cwsl_blog_row['blog_id'] );
			delete_option( 'cwsl_login_slug' );
			delete_option( 'cwsl_redirect_slug' );
			delete_option( 'cwsl_login_logo_attachment_id' );
			restore_current_blog();
		}
	}
	flush_rewrite_rules();
} else {
	delete_option( 'cwsl_login_slug' );
	delete_option( 'cwsl_redirect_slug' );
	delete_option( 'cwsl_login_logo_attachment_id' );
}
