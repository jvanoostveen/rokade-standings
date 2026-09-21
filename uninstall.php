<?php
/**
 * Removes everything the plugin stored when it is deleted from WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('rokade_standings_settings');
delete_transient('rokade_standings_index');
wp_clear_scheduled_hook('rokade_standings_refresh_index');

// Installs that ran the plugin under its earlier internal name and were never
// opened in the admin afterwards still hold these; an uninstall should not
// depend on that migration having run.
delete_option('schaken_standen_settings');
delete_transient('schaken_standen_index_v2');
delete_transient('schaken_standen_index_v3');
wp_clear_scheduled_hook('schaken_standen_refresh_index');
