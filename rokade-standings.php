<?php
/**
 * Plugin Name: Rokade Standen
 * Description: Indexeert Rokade-standenbestanden op disk en toont ze als toegankelijke, gestylede WordPress-tabs.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Author: Schaken in Hoogland
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/jvanoostveen/rokade-standings
 * Text Domain: rokade-standings
 */

if (!defined('ABSPATH')) {
	exit;
}

define('ROKADE_STANDINGS_VERSION', '1.0.0');
define('ROKADE_STANDINGS_FILE', __FILE__);
define('ROKADE_STANDINGS_DIR', plugin_dir_path(__FILE__));
define('ROKADE_STANDINGS_URL', plugin_dir_url(__FILE__));

require_once ROKADE_STANDINGS_DIR . 'includes/class-rokade-standings-index.php';
require_once ROKADE_STANDINGS_DIR . 'includes/class-rokade-standings-renderer.php';
require_once ROKADE_STANDINGS_DIR . 'includes/class-rokade-standings-admin.php';
require_once ROKADE_STANDINGS_DIR . 'includes/class-rokade-standings-updater.php';

function rokade_standings() {
	static $plugin = null;

	if (null === $plugin) {
		$plugin = new Rokade_Standings_Renderer(new Rokade_Standings_Index());
	}

	return $plugin;
}

function rokade_standings_activate() {
	if (!get_option('rokade_standings_settings')) {
		add_option('rokade_standings_settings', Rokade_Standings_Index::defaults());
	}

	rokade_standings_schedule_refresh();
}
register_activation_hook(__FILE__, 'rokade_standings_activate');

/**
 * The cron only exists to warm the transient before it expires, so running it
 * hourly against a fifteen-minute cache left visitors paying for the refresh.
 * Give it a recurrence that tracks the configured cache duration instead.
 */
add_filter('cron_schedules', function ($schedules) {
	$minutes = (new Rokade_Standings_Index())->cache_minutes();
	$schedules['rokade_standings_cache'] = array(
		'interval' => MINUTE_IN_SECONDS * $minutes,
		'display' => __('Rokade Standen cacheduur', 'rokade-standings'),
	);
	return $schedules;
});

function rokade_standings_schedule_refresh() {
	wp_clear_scheduled_hook('rokade_standings_refresh_index');
	wp_schedule_event(time() + MINUTE_IN_SECONDS, 'rokade_standings_cache', 'rokade_standings_refresh_index');
}

function rokade_standings_deactivate() {
	wp_clear_scheduled_hook('rokade_standings_refresh_index');
}
register_deactivation_hook(__FILE__, 'rokade_standings_deactivate');

add_action('plugins_loaded', function () {
	// Not a wordpress.org plugin, so nothing loads the text domain for us.
	load_plugin_textdomain('rokade-standings', false, dirname(plugin_basename(__FILE__)) . '/languages');
	Rokade_Standings_Admin::register();
	Rokade_Standings_Updater::register();
	rokade_standings()->register();
});

// The activation hook is the only place that schedules the refresh, and it does
// not run when the plugin files are replaced in place or when a cron plugin
// prunes the event. Put it back on the next request instead of silently
// falling back to visitors rescanning the directory.
add_action('init', function () {
	if (!wp_next_scheduled('rokade_standings_refresh_index')) {
		rokade_standings_schedule_refresh();
	}
});

add_action('rokade_standings_refresh_index', function () {
	$index = new Rokade_Standings_Index();
	$index->refresh();
});
