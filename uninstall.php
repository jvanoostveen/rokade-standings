<?php
/**
 * Removes everything the plugin stored when it is deleted from WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('schaken_standen_settings');
// Both index versions: an install upgraded from a single source path may still
// hold the older transient.
delete_transient('schaken_standen_index_v2');
delete_transient('schaken_standen_index_v3');
wp_clear_scheduled_hook('schaken_standen_refresh_index');
