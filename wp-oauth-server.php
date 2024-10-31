<?php

/**
 * Plugin Name: Posteria : Web to Wordpress to Social Media Content Suite
 * Plugin URI: https://posteria.fr
 * Version: 2.0.9
 * Description: Connect your Wordpress with Posteria and start sending smart content to your blog and networks.
 * Author: isoluce
 * Author URI: https://posteria.fr
 * Text Domain: wp-oauth
 *
 * @author  Laurent Lemonnier <Laurent@isoluce.net>
 * @package Posteria : Web to Wordpress to Social Media Content Suite
 */

defined('ABSPATH') or die('No script kiddies please!');

if (!defined('WPOAUTH_FILE')) {
	define('WPOAUTH_FILE', __FILE__);
}

if (!defined('WPOAUTH_VERSION')) {
	define('WPOAUTH_VERSION', '2.0.9');
}

// localize
add_action('plugins_loaded', 'wo_load_textdomain', 99);
function wo_load_textdomain()
{
	load_plugin_textdomain('wp-oauth', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

/**
 * 5.4 Strict Mode Temp Patch
 *
 * Since PHP 5.4, WP will through notices due to the way WP calls statically
 */
function wpoauth_server_register_files($suffix)
{
	// Only register and call select2 on on the page needed.
	if (
		$suffix == 'toplevel_page_wo_manage_clients'
		|| $suffix == 'admin_page_wo_edit_client'
		|| $suffix == 'oauth-server_page_wo_settings'
		|| $suffix == 'admin_page_wo_add_client'
	) {

		wp_register_script('wo_admin_select2', plugins_url('/assets/js/select2.min.js', __FILE__), array('jquery'));
		wp_register_style('wo_admin_select2', plugins_url('/assets/css/select2.min.css', __FILE__));
		wp_register_script('wo_admin_chosen', plugins_url('/assets/js/chosen.js', __FILE__), array('jquery'));
		wp_register_style('wo_admin', plugins_url('/assets/css/admin.css', __FILE__));
		wp_register_script(
			'wo_admin',
			plugins_url('/assets/js/admin.js', __FILE__),
			array(
				'jquery-ui-tabs',
				'jquery',
			)
		);

		wp_enqueue_script('wo_admin_select2');
		wp_enqueue_script('wo_admin_chosen');
		wp_enqueue_style('wo_admin_select2');
		wp_enqueue_script('wo_admin');
		wp_enqueue_style('wo_admin');
	}

	// Notices JS
	wp_register_script('wo_admin_notices', plugins_url('/assets/js/notices.js', __FILE__), array('jquery'));
	wp_enqueue_script('wo_admin_notices');
}

add_action('admin_enqueue_scripts', 'wpoauth_server_register_files');

require_once dirname(__FILE__) . '/includes/functions.php';
require_once dirname(__FILE__) . '/includes/cron.php';
require_once dirname(__FILE__) . '/wp-oauth-main.php';

/**
 * Adds/registers query vars
 *
 * @return void
 */
function wpoauth_server_register_query_vars()
{
	_wo_server_register_rewrites();

	global $wp;
	$wp->add_query_var('oauth');
	$wp->add_query_var('well-known');
	$wp->add_query_var('wpoauthincludes');
}

add_action('init', 'wpoauth_server_register_query_vars');

/**
 * Registers rewrites for OAuth2 Server
 *
 * - authorize
 * - token
 * - .well-known
 * - wpoauthincludes
 *
 * @return void
 */
function _wo_server_register_rewrites()
{
	add_rewrite_rule('^oauth/(.+)', 'index.php?oauth=$matches[1]', 'top');
	add_rewrite_rule('^.well-known/(.+)', 'index.php?well-known=$matches[1]', 'top');
	add_rewrite_rule('^wpoauthincludes/(.+)', 'index.php?wpoauthincludes=$matches[1]', 'top');
}

/**
 * [template_redirect_intercept description]
 *
 * @return [type] [description]
 */
function wpoauth_server_template_redirect_intercept($template)
{
	global $wp_query;

	if ($wp_query->get('oauth') || $wp_query->get('well-known')) {
		define('DOING_OAUTH', true);
		include_once dirname(__FILE__) . '/library/class-wo-api.php';
		exit;
	}

	return $template;
}

add_filter('template_include', 'wpoauth_server_template_redirect_intercept', 100);

/**
 * OAuth2 Server Activation
 *
 * @param [type] $network_wide [description]
 *
 * @return [type]               [description]
 */
function wpoauth_server_activation($network_wide)
{
	if (function_exists('is_multisite') && is_multisite() && $network_wide) {
		$mu_blogs = wp_get_sites();
		foreach ($mu_blogs as $mu_blog) {
			switch_to_blog($mu_blog['blog_id']);
			_wo_server_register_rewrites();
			flush_rewrite_rules();
		}
		restore_current_blog();
	} else {
		_wo_server_register_rewrites();
		flush_rewrite_rules();
	}

	// Schedule the cleanup workers
	wp_schedule_event(time(), 'hourly', 'wpo_global_cleanup');
}

register_activation_hook(__FILE__, 'wpoauth_server_activation');


/**
 * OAuth Server Deactivation
 *
 * @param [type] $network_wide [description]
 *
 * @return [type]               [description]
 */
function wpoauth_server_deactivation($network_wide)
{
	if (function_exists('is_multisite') && is_multisite() && $network_wide) {
		$mu_blogs = wp_get_sites();
		foreach ($mu_blogs as $mu_blog) {
			switch_to_blog($mu_blog['blog_id']);
			flush_rewrite_rules();
		}
		restore_current_blog();
	} else {
		flush_rewrite_rules();
	}

	// Remove the cleanup workers.
	wp_clear_scheduled_hook('wpo_global_cleanup');
}

register_deactivation_hook(__FILE__, 'wpoauth_server_deactivation');

register_activation_hook(__FILE__, array(new WO_Server(), 'setup'));
register_activation_hook(__FILE__, array(new WO_Server(), 'upgrade'));


register_activation_hook(__FILE__, 'fx_admin_notice_example_activation_hook');

/**
 * Runs only when the plugin is activated.
 * @since 0.1.0
 */
function fx_admin_notice_example_activation_hook()
{

	/* Create transient data */
	set_transient('fx-admin-notice-example', true, 5);
}


/* Add admin notice */
add_action('admin_notices', 'fx_admin_notice_example_notice');


/**
 * Admin Notice on Activation.
 * @since 0.1.0
 */
function fx_admin_notice_example_notice()
{

	/* Check transient, if available display notice */
	if (get_transient('fx-admin-notice-example')) {
?>
		<div class="updated notice is-dismissible">
			<p><strong>Merci pour votre installation, votre plugin est activé vous pouvez retourner sur Posteria ! </strong>.</p>
		</div>
<?php
		/* Delete transient, only display this notice once. */
		delete_transient('fx-admin-notice-example');
	}
}
