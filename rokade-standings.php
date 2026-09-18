<?php
/**
 * Plugin Name: Rokade Standen
 * Description: Indexeert Rokade-standenbestanden op disk en toont ze als toegankelijke, gestylede WordPress-tabs.
 * Version: 0.1.13
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Author: Schaken in Hoogland
 * License: GPL-2.0-or-later
 * Text Domain: schaken-standen
 */

if (!defined('ABSPATH')) {
	exit;
}

define('SCHAKEN_STANDEN_VERSION', '0.1.13');
define('SCHAKEN_STANDEN_FILE', __FILE__);
define('SCHAKEN_STANDEN_DIR', plugin_dir_path(__FILE__));
define('SCHAKEN_STANDEN_URL', plugin_dir_url(__FILE__));

require_once SCHAKEN_STANDEN_DIR . 'includes/class-rokade-standings-index.php';
require_once SCHAKEN_STANDEN_DIR . 'includes/class-rokade-standings-renderer.php';
require_once SCHAKEN_STANDEN_DIR . 'includes/class-rokade-standings-admin.php';

function schaken_standen() {
	static $plugin = null;

	if (null === $plugin) {
		$plugin = new Schaken_Standen_Renderer(new Schaken_Standen_Index());
	}

	return $plugin;
}

function schaken_standen_activate() {
	if (!get_option('schaken_standen_settings')) {
		add_option('schaken_standen_settings', array(
			'source_path' => '',
			'cache_minutes' => 15,
			'internal_group_order' => "Starters\nPupillen\nJunioren\nVerkenners\nMeester-/Kroon\nMeester\nKroon",
			'block_button_templates' => "doorgeefschaak | Blok {nummer}\nsnelschaken | Blok {nummer}",
		));
	}

	schaken_standen_schedule_refresh();
}
register_activation_hook(__FILE__, 'schaken_standen_activate');

/**
 * The cron only exists to warm the transient before it expires, so running it
 * hourly against a fifteen-minute cache left visitors paying for the refresh.
 * Give it a recurrence that tracks the configured cache duration instead.
 */
add_filter('cron_schedules', function ($schedules) {
	$minutes = (new Schaken_Standen_Index())->cache_minutes();
	$schedules['schaken_standen_cache'] = array(
		'interval' => MINUTE_IN_SECONDS * $minutes,
		'display' => __('Rokade Standen cacheduur', 'schaken-standen'),
	);
	return $schedules;
});

function schaken_standen_schedule_refresh() {
	wp_clear_scheduled_hook('schaken_standen_refresh_index');
	wp_schedule_event(time() + MINUTE_IN_SECONDS, 'schaken_standen_cache', 'schaken_standen_refresh_index');
}

function schaken_standen_deactivate() {
	wp_clear_scheduled_hook('schaken_standen_refresh_index');
}
register_deactivation_hook(__FILE__, 'schaken_standen_deactivate');

add_action('plugins_loaded', function () {
	Schaken_Standen_Admin::register();
	schaken_standen()->register();
});

add_action('schaken_standen_refresh_index', function () {
	$index = new Schaken_Standen_Index();
	$index->refresh();
});
