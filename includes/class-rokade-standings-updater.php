<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Serves updates from the GitHub releases of this repository.
 *
 * The plugin is not on wordpress.org, so nothing tells an install that a newer
 * version exists. The release workflow publishes an update.json next to every
 * release zip, and WordPress asks us about it through the update_plugins_{host}
 * filter because the plugin header carries an Update URI on github.com.
 *
 * Everything here has to survive an unreachable feed without a fatal error or a
 * stalled admin screen: while the repository is still private the feed answers
 * 404, and once it is public a site can still be offline or behind a proxy. A
 * failed check is cached briefly and simply leaves WordPress believing the
 * installed version is current.
 */
class Rokade_Standings_Updater {
	const REPOSITORY = 'jvanoostveen/rokade-standings';
	const SLUG = 'rokade-standings';
	const CACHE_KEY = 'rokade_standings_update';
	/** Marker stored in place of a release when the feed could not be read. */
	const UNAVAILABLE = 'unavailable';

	public static function register() {
		// The filter name is derived from the host in the Update URI header.
		add_filter('update_plugins_github.com', array(__CLASS__, 'update'), 10, 3);
		add_filter('plugins_api', array(__CLASS__, 'details'), 10, 3);
		add_action('upgrader_process_complete', array(__CLASS__, 'flush'));
		// "Opnieuw controleren" on Dashboard → Updates clears the core transients
		// but not ours, which would keep serving the answer from before the fix
		// the admin is checking for. Run before the update check itself.
		add_action('admin_init', array(__CLASS__, 'flush_forced'), 1);
	}

	/** The stable URL GitHub keeps pointing at the newest release's asset. */
	public static function feed_url() {
		return 'https://github.com/' . self::REPOSITORY . '/releases/latest/download/update.json';
	}

	/**
	 * Answers WordPress's question about this plugin. Returning the unchanged
	 * $update (false) means "nothing known", which is exactly what should happen
	 * when the feed is unreachable.
	 */
	public static function update($update, $plugin_data, $plugin_file) {
		if (plugin_basename(ROKADE_STANDINGS_FILE) !== $plugin_file) {
			// Another plugin that also updates from github.com.
			return $update;
		}

		$release = self::release();
		if (!$release) {
			return $update;
		}

		// Core compares the version itself and fills in id, plugin and
		// new_version; they are set here too so the array stands on its own.
		return array(
			'id' => isset($plugin_data['UpdateURI']) ? $plugin_data['UpdateURI'] : 'https://github.com/' . self::REPOSITORY,
			'slug' => self::SLUG,
			'plugin' => $plugin_file,
			'version' => $release['version'],
			'new_version' => $release['version'],
			'url' => $release['url'],
			'package' => $release['package'],
			'tested' => $release['tested'],
			'requires' => $release['requires'],
			'requires_php' => $release['requires_php'],
			'icons' => self::icons(),
		);
	}

	/**
	 * The icon Dashboard → Updates shows next to the update. It comes from the
	 * installed copy rather than the feed, so it needs no extra request and
	 * cannot be swapped by whoever controls the release.
	 */
	private static function icons() {
		return array('svg' => ROKADE_STANDINGS_URL . 'assets/icon.svg');
	}

	/**
	 * Fills the "Details bekijken" modal. Without this the link that WordPress
	 * renders next to the update notice would land on an error page.
	 */
	public static function details($result, $action, $args) {
		if ('plugin_information' !== $action || !isset($args->slug) || self::SLUG !== $args->slug) {
			return $result;
		}

		$release = self::release();
		if (!$release) {
			return $result;
		}

		return (object) array(
			'name' => $release['name'],
			'slug' => self::SLUG,
			'version' => $release['version'],
			'author' => $release['author'],
			'homepage' => $release['homepage'],
			'requires' => $release['requires'],
			'tested' => $release['tested'],
			'requires_php' => $release['requires_php'],
			'last_updated' => $release['last_updated'],
			'download_link' => $release['package'],
			'sections' => $release['sections'],
		);
	}

	public static function flush() {
		delete_transient(self::CACHE_KEY);
	}

	public static function flush_forced() {
		if (!isset($_GET['force-check']) || !current_user_can('update_plugins')) {
			return;
		}

		self::flush();
	}

	/** The published release as a normalised array, or false when unknown. */
	private static function release() {
		$cached = get_transient(self::CACHE_KEY);
		if (is_array($cached)) {
			return $cached;
		}
		if (self::UNAVAILABLE === $cached) {
			return false;
		}

		$response = wp_remote_get(self::feed_url(), array(
			'timeout' => 10,
			'redirection' => 5,
			'headers' => array('Accept' => 'application/json'),
			'user-agent' => 'Rokade Standen/' . ROKADE_STANDINGS_VERSION . '; ' . home_url('/'),
		));

		$release = self::parse($response);
		if (!$release) {
			// Remember the failure for an hour: without it every admin screen
			// would wait on the same doomed request.
			set_transient(self::CACHE_KEY, self::UNAVAILABLE, HOUR_IN_SECONDS);
			return false;
		}

		set_transient(self::CACHE_KEY, $release, 12 * HOUR_IN_SECONDS);

		return $release;
	}

	/** Turns the HTTP response into a release array, or false if it is unusable. */
	private static function parse($response) {
		if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
			return false;
		}

		$feed = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($feed) || empty($feed['version']) || empty($feed['package'])) {
			return false;
		}

		$version = (string) $feed['version'];
		if (!preg_match('/^[0-9][0-9A-Za-z.+-]*$/', $version)) {
			return false;
		}

		// WordPress hands the package straight to the upgrader, so only accept a
		// download from the hosts GitHub serves release assets from.
		$package = esc_url_raw((string) $feed['package'], array('https'));
		$host = $package ? wp_parse_url($package, PHP_URL_HOST) : '';
		if (!in_array($host, array('github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'), true)) {
			return false;
		}

		$text = static function ($value) {
			return sanitize_text_field((string) $value);
		};
		$sections = isset($feed['sections']) && is_array($feed['sections']) ? $feed['sections'] : array();

		return array(
			'name' => isset($feed['name']) ? $text($feed['name']) : __('Rokade Standen', 'rokade-standings'),
			'version' => $version,
			'package' => $package,
			'url' => isset($feed['url']) ? esc_url_raw((string) $feed['url'], array('https')) : 'https://github.com/' . self::REPOSITORY,
			'homepage' => isset($feed['homepage']) ? esc_url_raw((string) $feed['homepage'], array('https')) : 'https://github.com/' . self::REPOSITORY,
			'author' => isset($feed['author']) ? wp_kses($feed['author'], array('a' => array('href' => array()))) : '',
			'requires' => isset($feed['requires']) ? $text($feed['requires']) : '',
			'tested' => isset($feed['tested']) ? $text($feed['tested']) : '',
			'requires_php' => isset($feed['requires_php']) ? $text($feed['requires_php']) : '',
			'last_updated' => isset($feed['last_updated']) ? $text($feed['last_updated']) : '',
			'sections' => array(
				'description' => isset($sections['description']) ? wp_kses_post($sections['description']) : '',
				'changelog' => isset($sections['changelog']) ? wp_kses_post($sections['changelog']) : '',
			),
		);
	}
}
