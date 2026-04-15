=== CWS Login ===
Contributors: bibekraja
Author URI: https://profiles.wordpress.org/bibekraja/
Plugin URI: https://wordpress.org/plugins/cws-login
Tags: login, security, wp-login, branding, custom login
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use a custom login URL, block default wp-login exposure, redirect anonymous wp-admin visits, and brand the login screen with your logo.

== Description ==

**CWS Login (Custom WordPress Security Login)** helps harden and customize how people reach the WordPress login screen:

* **Custom login path** — Visitors sign in at a URL you choose (for example `https://example.com/your-secret-slug/`) instead of the default `wp-login.php` address.
* **Block direct access to the old login URL** — Requests that target the default `wp-login.php` entry point are handled so casual visitors use your custom login address instead.
* **Redirect anonymous admin attempts** — Users who are not logged in and try to load wp-admin (or related entry points) can be sent to another path on your site, often your 404 page.
* **Login screen logo** — Upload a company logo from the Media Library to replace the WordPress logo on the login page.

**Important:** This plugin changes URLs and routing. After saving settings, bookmark your new login URL. Keep a recovery plan (FTP/SSH/database) if you forget the slug. Only one plugin should customize the login URL at a time—if another plugin that changes the login address is already active, CWS Login will stay inactive for that site until you deactivate the other plugin.

= Multisite =

Network-wide defaults can be set in **Network Admin → Settings** when the plugin is network-activated. Individual sites may override values from **CWS Login** in the site admin.

= Privacy =

The plugin stores:

* Your chosen login and redirect path segments (site options).
* The attachment ID of an optional logo image (site option).

It does not send data to external services. The login page may display your chosen image from the Media Library (public URL, same as any uploaded file).

Suggested policy text for your privacy notice: *This site may use a custom login address and optional branding image on the login page.*

== Credits ==

* **CWS Login** — Original plugin code by Bibek Raja, licensed under GPLv2 or later.
* **WPS Hide Login** — Acknowledged as reference / prior art for the general approach to custom login URLs, masking `wp-login.php` for visitors, and redirecting anonymous wp-admin traffic. WPS Hide Login is GPL-2.0+ and available at https://wordpress.org/plugins/wps-hide-login/ .

== Installation ==

1. Upload the `cws-login` folder to `/wp-content/plugins/` or install through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu.
3. Go to **CWS Login** in the admin menu, set your login path and redirect path, and save.
4. Bookmark the login URL shown in the success message.

== Frequently Asked Questions ==

= I locked myself out. What now? =

Deactivate the plugin via FTP/SFTP (for example by renaming the `cws-login` folder), WP-CLI (`wp plugin deactivate cws-login`), or by removing its options in the database (`cwsl_login_slug`, `cwsl_redirect_slug`). Then log in via the default URL or restore from backup.

= Can I use another plugin that also changes the login URL? =

No—not at the same time. Only one plugin should control the login address. If you already use a different plugin that customizes the WordPress login URL, deactivate it before relying on CWS Login. When CWS Login detects another active plugin that alters the same login routing, it will not load its own rules so your site does not get conflicting behavior.

= Does this replace strong passwords or two-factor authentication? =

No. It obscures the login URL and reduces casual probing; it is not a substitute for good passwords, updates, or additional authentication.

= Can I register extra conflicting plugins in code? =

Yes. Developers can use the `cwsl_conflicting_plugin_basenames` filter to pass an array of plugin basenames (e.g. `folder/plugin.php`) that should disable CWS Login when active, in addition to the default list.

== Screenshots ==

1. CWS Login settings screen — custom login path, redirect path for blocked access, and optional login logo.

== Changelog ==

= 1.0.0 =
* Security and hardening: sanitized admin request variables, capability checks on redirects and notices, `wp_safe_redirect` where appropriate, image-only logo validation with `edit_post` capability, stricter redirect slug validation, safer admin JS (DOM APIs instead of HTML string concatenation).
* Privacy: suggested policy text via WordPress Privacy Policy guide.
* Added readme.txt and directory index.php shields; removed uninstall `OPTIMIZE TABLE` for host compatibility.
* Credits: copyright notice in plugin header; acknowledgment of WPS Hide Login as reference for login URL redirection.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Maintenance and security hardening release. Recommended for all users.
