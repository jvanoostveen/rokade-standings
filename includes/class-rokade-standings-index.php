<?php

if (!defined('ABSPATH')) {
	exit;
}

/** Reads the small Index.htm files once and caches their metadata in a transient. */
class Schaken_Standen_Index {
	const CACHE_KEY = 'schaken_standen_index_v3';

	public function settings() {
		$settings = get_option('schaken_standen_settings', array());
		if (!is_array($settings)) {
			$settings = array();
		}

		// Installs from before multiple sources stored one bare path. Turn it into
		// the first labelled source on read; the next save writes the new shape.
		if (!isset($settings['sources']) && isset($settings['source_path'])) {
			$legacy = untrailingslashit(trim((string) $settings['source_path']));
			$settings['sources'] = '' === $legacy ? '' : $legacy . ' | ' . __('Standen', 'schaken-standen');
		}
		unset($settings['source_path']);

		return wp_parse_args($settings, array(
			'sources' => '',
			'cache_minutes' => 15,
			'internal_group_order' => "Starters\nPupillen\nJunioren\nVerkenners\nMeester-/Kroon\nMeester\nKroon",
			'block_button_templates' => "doorgeefschaak | Blok {nummer}\nsnelschaken | Blok {nummer}",
		));
	}

	/**
	 * The configured sources as id => array('id', 'label', 'path').
	 *
	 * Read straight from the settings rather than from the cached index, so a
	 * path that was removed stops serving files immediately instead of after the
	 * transient expires.
	 */
	public function sources() {
		$lines = preg_split('/\r\n|\r|\n/', (string) $this->settings()['sources']);
		$sources = array();

		foreach ($lines as $line) {
			$parts = array_map('trim', explode('|', $line, 2));
			$path = untrailingslashit($parts[0]);
			if ('' === $path) {
				continue;
			}

			$label = (isset($parts[1]) && '' !== $parts[1]) ? $parts[1] : basename($path);
			$id = sanitize_title($label);
			if ('' === $id) {
				$id = 'bron';
			}

			// Two sources may carry the same label; the id is what a shortcode and
			// a deep link refer to, so it has to stay unique.
			$candidate = $id;
			$suffix = 2;
			while (isset($sources[$candidate])) {
				$candidate = $id . '-' . $suffix;
				$suffix++;
			}

			$sources[$candidate] = array('id' => $candidate, 'label' => $label, 'path' => $path);
		}

		return $sources;
	}

	public function source_path($source_id) {
		$sources = $this->sources();
		return isset($sources[$source_id]) ? $sources[$source_id]['path'] : '';
	}

	public function default_source_id() {
		$ids = array_keys($this->sources());
		return $ids ? $ids[0] : '';
	}

	public function get_index($force = false) {
		if (!$force) {
			$cached = get_transient(self::CACHE_KEY);
			if (is_array($cached) && isset($cached['sources'])) {
				return $cached;
			}
		}

		return $this->refresh();
	}

	public function refresh() {
		$index = array('sources' => array(), 'updated_at' => time());

		foreach ($this->sources() as $id => $source) {
			$index['sources'][$id] = array(
				'label' => $source['label'],
				'path' => $source['path'],
				'seasons' => $this->scan($source['path']),
			);
		}

		// Cache the misses too, otherwise every page view stats an unreachable
		// (possibly networked) path again.
		$this->store($index);
		return $index;
	}

	public function cache_minutes() {
		return min(1440, max(1, absint($this->settings()['cache_minutes'])));
	}

	public function clear() {
		delete_transient(self::CACHE_KEY);
	}

	private function scan($root) {
		$seasons = array();
		if (!$root || !is_dir($root) || !is_readable($root)) {
			return $seasons;
		}

		$season_dirs = glob($root . '/*', GLOB_ONLYDIR);
		if (!$season_dirs) {
			$season_dirs = array();
		}

		foreach ($season_dirs as $season_path) {
			$season = basename($season_path);
			$competitions = array();
			$files = glob($season_path . '/*/C*Index.htm');
			if (!$files) {
				$files = array();
			}

			foreach ($files as $file) {
				$item = $this->make_competition($root, $season, $file);
				if ($item) {
					$competitions[] = $item;
				}
			}

			usort($competitions, array($this, 'sort_competitions'));
			if ($competitions) {
				$seasons[$season] = $competitions;
			}
		}

		uksort($seasons, 'version_compare');
		return $seasons;
	}

	private function store($index) {
		set_transient(self::CACHE_KEY, $index, MINUTE_IN_SECONDS * $this->cache_minutes());
	}

	private function make_competition($root, $season, $file) {
		$title = $this->read_title($file);
		if ('' === $title) {
			return null;
		}

		$relative = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
		$relative_in_season = ltrim(substr($relative, strlen($season)), DIRECTORY_SEPARATOR);
		$directory = dirname($relative_in_season);
		$filename = basename($file);
		preg_match('/^C(\d+)Index\.htm$/i', $filename, $matches);
		$prefix = isset($matches[1]) ? (int) $matches[1] : 9999;
		$ranking = preg_replace('/Index\.htm$/i', 'Ranglijst.htm', $filename);
		$cross_table = preg_replace('/Index\.htm$/i', 'Kruistabel.htm', $filename);
		$score_table = preg_replace('/Index\.htm$/i', 'Scoretabel.htm', $filename);

		// The ranking is what the first render shows, so a competition without a
		// readable one would only ever produce an error notice. Skip it.
		if (!is_readable(dirname($file) . DIRECTORY_SEPARATOR . $ranking)) {
			return null;
		}
		$ranking_relative = $directory . '/' . $ranking;

		return array(
			'id' => sanitize_title($season . '-' . $directory . '-' . $filename),
			'title' => $title,
			'category' => $this->category_for($title),
			'category_label' => $this->category_label($this->category_for($title)),
			'file' => str_replace(DIRECTORY_SEPARATOR, '/', $relative_in_season),
			'ranking_file' => str_replace(DIRECTORY_SEPARATOR, '/', $ranking_relative),
			'cross_file' => is_readable(dirname($file) . DIRECTORY_SEPARATOR . $cross_table) ? str_replace(DIRECTORY_SEPARATOR, '/', $directory . '/' . $cross_table) : '',
			'score_file' => is_readable(dirname($file) . DIRECTORY_SEPARATOR . $score_table) ? str_replace(DIRECTORY_SEPARATOR, '/', $directory . '/' . $score_table) : '',
			'number' => $prefix,
		);
	}

	/**
	 * Rokade exports are Windows-1252; everything downstream (transients, esc_html)
	 * assumes UTF-8, so convert before the bytes leave the file.
	 */
	public function to_utf8($html) {
		if (1 === preg_match('//u', $html)) {
			return $html;
		}
		if (function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
		}
		if (function_exists('iconv')) {
			$converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $html);
			if (false !== $converted) {
				return $converted;
			}
		}
		return $html;
	}

	private function read_title($file) {
		$contents = @file_get_contents($file, false, null, 0, 8192);
		if (false === $contents || !preg_match('/<title[^>]*>(.*?)<\/title>/is', $contents, $matches)) {
			return '';
		}

		$title = $this->to_utf8($matches[1]);
		return trim(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}

	private function category_for($title) {
		$normalized = strtolower(remove_accents($title));
		if (false !== strpos($normalized, 'doorgeef')) {
			return 'doorgeefschaak';
		}
		if (false !== strpos($normalized, 'snelscha')) {
			return 'snelschaken';
		}
		return 'interne-competitie';
	}

	private function category_label($category) {
		$labels = array(
			'interne-competitie' => __('Interne competitie', 'schaken-standen'),
			'doorgeefschaak' => __('Doorgeefschaak', 'schaken-standen'),
			'snelschaken' => __('Snelschaken', 'schaken-standen'),
		);
		return isset($labels[$category]) ? $labels[$category] : $category;
	}

	private function sort_competitions($a, $b) {
		$order = array('interne-competitie' => 0, 'doorgeefschaak' => 1, 'snelschaken' => 2);
		$category_order = $order[$a['category']] <=> $order[$b['category']];
		return $category_order ?: ($a['number'] <=> $b['number']);
	}
}
