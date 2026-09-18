<?php

if (!defined('ABSPATH')) {
	exit;
}

/** Reads the small Index.htm files once and caches their metadata in a transient. */
class Schaken_Standen_Index {
	const CACHE_KEY = 'schaken_standen_index_v1';

	public function settings() {
		$settings = get_option('schaken_standen_settings', array());
		return wp_parse_args($settings, array(
			'source_path' => '',
			'cache_minutes' => 15,
		));
	}

	public function source_path() {
		$settings = $this->settings();
		return untrailingslashit((string) $settings['source_path']);
	}

	public function get_index($force = false) {
		if (!$force) {
			$cached = get_transient(self::CACHE_KEY);
			if (is_array($cached)) {
				return $cached;
			}
		}

		return $this->refresh();
	}

	public function refresh() {
		$root = $this->source_path();
		$index = array('seasons' => array(), 'updated_at' => time());

		if (!$root || !is_dir($root) || !is_readable($root)) {
			return $index;
		}

		$season_dirs = glob($root . '/*', GLOB_ONLYDIR);
		if (!$season_dirs) {
			$season_dirs = array();
		}

		foreach ($season_dirs as $season_path) {
			$season = basename($season_path);
			if (!preg_match('/^\d{4}-\d{4}$/', $season)) {
				continue;
			}

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
				$index['seasons'][$season] = $competitions;
			}
		}

		uksort($index['seasons'], 'version_compare');
		$minutes = max(1, absint($this->settings()['cache_minutes']));
		set_transient(self::CACHE_KEY, $index, MINUTE_IN_SECONDS * $minutes);
		return $index;
	}

	public function clear() {
		delete_transient(self::CACHE_KEY);
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
		$ranking_relative = $directory . '/' . $ranking;

		return array(
			'id' => sanitize_title($season . '-' . $directory . '-' . $filename),
			'title' => $title,
			'category' => $this->category_for($title),
			'category_label' => $this->category_label($this->category_for($title)),
			'file' => str_replace(DIRECTORY_SEPARATOR, '/', $relative_in_season),
			'ranking_file' => str_replace(DIRECTORY_SEPARATOR, '/', $ranking_relative),
			'number' => $prefix,
		);
	}

	private function read_title($file) {
		$contents = @file_get_contents($file, false, null, 0, 8192);
		if (false === $contents || !preg_match('/<title[^>]*>(.*?)<\/title>/is', $contents, $matches)) {
			return '';
		}

		return trim(html_entity_decode(wp_strip_all_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'ISO-8859-1'));
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
		return $labels[$category];
	}

	private function sort_competitions($a, $b) {
		$order = array('interne-competitie' => 0, 'doorgeefschaak' => 1, 'snelschaken' => 2);
		$category_order = $order[$a['category']] <=> $order[$b['category']];
		return $category_order ?: ($a['number'] <=> $b['number']);
	}
}
