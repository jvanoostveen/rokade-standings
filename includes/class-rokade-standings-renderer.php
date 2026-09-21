<?php

if (!defined('ABSPATH')) {
	exit;
}

class Rokade_Standings_Renderer {
	private $index;
	private $group_rules = null;
	private $block_button_rules = null;

	public function __construct($index) {
		$this->index = $index;
	}

	public function register() {
		add_shortcode('rokade', array($this, 'shortcode'));
		add_action('init', array($this, 'register_assets'), 5);
		add_action('init', array($this, 'register_block'));
		add_action('enqueue_block_editor_assets', array($this, 'localize_block_options'));
		add_action('template_redirect', array($this, 'serve_source_file'));
	}

	public function register_assets() {
		wp_register_style('rokade-standings', ROKADE_STANDINGS_URL . 'assets/rokade-standings.css', array(), $this->asset_version('rokade-standings.css'));
		wp_register_script('rokade-standings', ROKADE_STANDINGS_URL . 'assets/rokade-standings.js', array(), $this->asset_version('rokade-standings.js'), true);
		// The same strings the server-rendered markup uses, so both stay in step once translated.
		wp_localize_script('rokade-standings', 'rokadeStandingsL10n', array(
			// The same base the server-rendered links use, so a fetch never drags
			// the current page's own query string (preview, search) along.
			'endpoint' => home_url('/'),
			'ranking' => __('Ranglijst', 'rokade-standings'),
			'cross' => __('Kruistabel', 'rokade-standings'),
			'score' => __('Scoretabel', 'rokade-standings'),
			'back' => __('Terug naar ranglijst', 'rokade-standings'),
			'frameTitle' => __('Standen', 'rokade-standings'),
			'loadError' => __('Dit standenbestand kan niet worden geladen.', 'rokade-standings'),
		));
		wp_register_script(
			'rokade-standings-block-editor',
			ROKADE_STANDINGS_URL . 'assets/rokade-standings-block.js',
			array('wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'),
			$this->asset_version('rokade-standings-block.js'),
			true
		);
	}

	/**
	 * Cache-busting version for one file in assets/. The modification time
	 * changes with every edit, where the plugin version only changes on release;
	 * a browser would otherwise keep an outdated editor script.
	 */
	private function asset_version($file) {
		$mtime = @filemtime(ROKADE_STANDINGS_DIR . 'assets/' . $file);
		return $mtime ? (string) $mtime : ROKADE_STANDINGS_VERSION;
	}

	/** Registers the dynamic Gutenberg equivalent of the [rokade] shortcode. */
	public function register_block() {
		if (!function_exists('register_block_type')) {
			return;
		}

		// Everything but the callback lives in blocks/standings/block.json, so the
		// editor can read the same definition the server registers.
		$block = register_block_type(ROKADE_STANDINGS_DIR . 'blocks/standings', array(
			'render_callback' => array($this, 'render_block'),
		));

		// Pages saved before the rename still contain the block under its earlier
		// name. Keep rendering and editing those instead of showing a missing
		// block; the editor script registers the same alias with inserter: false.
		if ($block) {
			register_block_type('schaken-standen/rokade', array(
				'title' => $block->title,
				'attributes' => $block->attributes,
				'supports' => array_merge((array) $block->supports, array('inserter' => false)),
				'editor_script_handles' => $block->editor_script_handles,
				'style_handles' => $block->style_handles,
				'editor_style_handles' => $block->editor_style_handles,
				'render_callback' => array($this, 'render_block'),
			));
		}
	}

	/** Loads index-derived dropdown choices only in the block editor. */
	public function localize_block_options() {
		wp_localize_script('rokade-standings-block-editor', 'rokadeStandingsBlock', $this->block_options());
	}

	/** Supplies editor dropdown options from the current, cached standings index. */
	private function block_options() {
		$data = $this->index->get_index();
		$sources = array();
		foreach ($data['sources'] as $id => $source) {
			$season_categories = array();
			foreach ($source['seasons'] as $season => $competitions) {
				$categories = array();
				foreach ($competitions as $competition) {
					$categories[$competition['category']] = $competition['category_label'];
				}
				$season_categories[$season] = $categories;
			}

			$sources[] = array(
				'id' => $id,
				'label' => $source['label'],
				'seasons' => array_keys($source['seasons']),
				'seasonCategories' => $season_categories,
			);
		}

		return array(
			'sources' => $sources,
			'labels' => array(
				'defaultSource' => __('Eerste bron', 'rokade-standings'),
				'latestSeason' => __('Meest recente seizoen', 'rokade-standings'),
				'allCategories' => __('Alle competities', 'rokade-standings'),
				'inline' => __('Inline (in de pagina)', 'rokade-standings'),
				'iframe' => __('Oorspronkelijke Rokade-weergave', 'rokade-standings'),
			),
		);
	}

	public function render_block($attributes) {
		return $this->shortcode(is_array($attributes) ? $attributes : array());
	}

	public function shortcode($attributes) {
		$attributes = shortcode_atts(array(
			'bron' => '',
			'seizoen' => '',
			'categorie' => '',
			'modus' => 'inline',
		), $attributes, 'rokade');
		$data = $this->index->get_index();
		$source = $this->choose_source($data['sources'], $attributes['bron']);
		$season = $source ? $this->choose_season($data['sources'][$source]['seasons'], $attributes['seizoen']) : '';

		if (!$season) {
			return current_user_can('manage_options')
				? '<p class="rokade-standings__notice">' . esc_html__('Er zijn geen leesbare standen gevonden. Stel de bronpaden in onder Instellingen → Rokade Standen.', 'rokade-standings') . '</p>'
				: '';
		}

		$competitions = $data['sources'][$source]['seasons'][$season];
		if ($attributes['categorie']) {
			$category = sanitize_title($attributes['categorie']);
			$competitions = array_values(array_filter($competitions, function ($competition) use ($category) {
				return $competition['category'] === $category;
			}));
		}
		if (!$competitions) {
			return '';
		}

		wp_enqueue_style('rokade-standings');
		wp_enqueue_script('rokade-standings');
		return $this->render($source, $season, $competitions, 'iframe' === $attributes['modus'], empty($attributes['categorie']));
	}

	/**
	 * Without an explicit source, prefer the first one that actually yielded
	 * standings: a site whose first configured path is temporarily unreachable
	 * should still show the other ones rather than an empty block.
	 */
	private function choose_source($sources, $requested) {
		$requested = is_scalar($requested) ? sanitize_title((string) $requested) : '';
		if ('' !== $requested) {
			return isset($sources[$requested]) ? $requested : '';
		}
		foreach ($sources as $id => $source) {
			if ($source['seasons']) {
				return $id;
			}
		}
		$available = array_keys($sources);
		return $available ? $available[0] : '';
	}

	private function choose_season($seasons, $requested) {
		if ($requested) {
			return isset($seasons[$requested]) ? $requested : '';
		}
		$available = array_keys($seasons);
		return $available ? end($available) : '';
	}

	private function render($source, $season, $competitions, $iframe, $show_categories = true) {
		$categories = array();
		foreach ($competitions as $competition) {
			if ('interne-competitie' !== $competition['category']) {
				$competition['display_title'] = $this->category_display_title($competition['category'], $competition['title']);
			}
			$categories[$competition['category']]['label'] = $competition['category_label'];
			$categories[$competition['category']]['items'][] = $competition;
		}
		foreach ($categories as $key => $category) {
			$categories[$key]['periods'] = $this->periods_for_category($season, $key, $category['items']);
		}

		// Assign deep-link keys across the whole instance at once, so collisions
		// between categories and periods can be resolved.
		$used_keys = array();
		foreach ($categories as $category_key => $category) {
			foreach ($category['periods'] as $period_index => $period) {
				foreach ($period['items'] as $item_index => $item) {
					$categories[$category_key]['periods'][$period_index]['items'][$item_index]['url_key'] = $this->competition_url_key($item, $used_keys);
				}
			}
		}

		$first_category = array_key_first($categories);
		$first_item = $categories[$first_category]['periods'][0]['items'][0];
		$instance = 'rokade-standings-' . wp_generate_uuid4();

		ob_start();
		?>
		<section class="rokade-standings" id="<?php echo esc_attr($instance); ?>" data-mode="<?php echo $iframe ? 'iframe' : 'inline'; ?>" data-source="<?php echo esc_attr($source); ?>" data-season="<?php echo esc_attr($season); ?>">
			<?php if ($show_categories) : ?>
				<div class="rokade-standings__categories" role="group" aria-label="<?php esc_attr_e('Soort competitie', 'rokade-standings'); ?>">
					<?php foreach ($categories as $key => $category) : ?>
						<button type="button" class="rokade-standings__category<?php echo $key === $first_category ? ' is-active' : ''; ?>" data-category="<?php echo esc_attr($key); ?>" aria-pressed="<?php echo $key === $first_category ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($instance . '-' . $key); ?>"><?php echo esc_html($category['label']); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php foreach ($categories as $key => $category) : ?>
				<?php $content_id = $instance . '-' . $key . '-content'; ?>
				<div class="rokade-standings__group<?php echo $key === $first_category ? ' is-active' : ''; ?>" id="<?php echo esc_attr($instance . '-' . $key); ?>" data-category-panel="<?php echo esc_attr($key); ?>">
					<?php $tab_number = 0; ?>
					<?php foreach ($category['periods'] as $period) : ?>
						<section class="rokade-standings__period">
							<?php if ($period['label']) : ?><h3 class="rokade-standings__period-title"><?php echo esc_html($period['label']); ?></h3><?php endif; ?>
							<div class="rokade-standings__tabs" role="group" aria-label="<?php echo esc_attr($period['label'] ? $period['label'] : $category['label']); ?>">
								<?php foreach ($period['items'] as $competition) : ?>
									<?php $is_active = 0 === $tab_number++; ?>
									<button type="button" class="rokade-standings__tab<?php echo $is_active ? ' is-active' : ''; ?>" data-file="<?php echo esc_attr($competition['ranking_file']); ?>" data-competition="<?php echo esc_attr($competition['url_key']); ?>" data-cross-file="<?php echo esc_attr($competition['cross_file']); ?>" data-score-file="<?php echo esc_attr($competition['score_file']); ?>" aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($content_id); ?>"><?php echo esc_html(isset($competition['display_title']) ? $competition['display_title'] : $competition['title']); ?></button>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
					<div class="rokade-standings__views" role="group" aria-label="<?php esc_attr_e('Weergave', 'rokade-standings'); ?>">
						<?php if ($key === $first_category) : ?>
							<?php echo $this->render_view_buttons($first_item, $content_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
					<div class="rokade-standings__content" id="<?php echo esc_attr($content_id); ?>" role="region" aria-label="<?php esc_attr_e('Standen', 'rokade-standings'); ?>" aria-live="polite">
						<?php if ($key === $first_category) : ?>
							<?php echo $this->render_file($source, $season, $first_item['ranking_file'], $iframe); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Deep-link key for one competition. Titles are not guaranteed unique across
	 * a season -- the same group name can appear in two directories -- so keys
	 * are disambiguated against the ones already handed out for this render.
	 */
	private function competition_url_key($competition, &$used) {
		$key = sanitize_title($competition['title']);
		if ('' === $key) {
			$key = 'competitie-' . absint($competition['number']);
		}

		$candidate = $key;
		$suffix = 2;
		while (isset($used[$candidate])) {
			$candidate = $key . '-' . $suffix;
			$suffix++;
		}
		$used[$candidate] = true;
		return $candidate;
	}

	/** Category => button template, resolved once per render instead of per competition. */
	private function block_button_rules() {
		if (null !== $this->block_button_rules) {
			return $this->block_button_rules;
		}

		$settings = $this->index->settings();
		$lines = preg_split('/\\r\\n|\\r|\\n/', (string) $settings['block_button_templates']);
		$rules = array();
		foreach ($lines as $line) {
			$parts = array_map('trim', explode('|', $line, 2));
			if (2 !== count($parts) || '' === $parts[1]) {
				continue;
			}
			// First line wins, as it did when this was a linear search.
			$key = sanitize_title($parts[0]);
			if (!isset($rules[$key])) {
				$rules[$key] = $parts[1];
			}
		}
		$this->block_button_rules = $rules;
		return $this->block_button_rules;
	}

	private function category_display_title($category, $title) {
		if (!in_array($category, array('doorgeefschaak', 'snelschaken'), true) || !preg_match('/\\bblok\\s+(\\d+)\\b/ui', $title, $matches)) {
			return $title;
		}

		$rules = $this->block_button_rules();
		if (!isset($rules[$category])) {
			return $title;
		}
		return str_replace('{nummer}', (string) ((int) $matches[1]), $rules[$category]);
	}

	private function periods_for_category($season, $category, $items) {
		if ('interne-competitie' !== $category) {
			return array(array('label' => '', 'items' => $items));
		}

		$periods = array();
		foreach ($items as $item) {
			$period = $this->period_from_title($season, $item['title']);
			if (!isset($periods[$period['key']])) {
				$periods[$period['key']] = array(
					'label' => $period['label'],
					'sort' => $period['sort'],
					'items' => array(),
				);
			}
			$match = $this->match_internal_group($item['title']);
			$item['display_title'] = $match['label'];
			$item['group_position'] = $match['position'];
			$periods[$period['key']]['items'][] = $item;
		}

		foreach ($periods as &$period) {
			usort($period['items'], array($this, 'sort_internal_items'));
		}
		unset($period);
		usort($periods, function ($a, $b) {
			return $b['sort'] <=> $a['sort'];
		});
		return array_values($periods);
	}

	private function period_from_title($season, $title) {
		if (preg_match('/\\b(voorjaar|najaar)\\s+(\\d{4})\\b/ui', $title, $matches)) {
			$season_part = strtolower($matches[1]);
			$year = (int) $matches[2];
			$label = ('voorjaar' === $season_part ? __('Voorjaar', 'rokade-standings') : __('Najaar', 'rokade-standings')) . ' ' . $year;
			return array(
				'key' => $season_part . '-' . $year,
				'label' => $label,
				'sort' => ($year * 10) + ('voorjaar' === $season_part ? 2 : 1),
			);
		}

		$sort = 0;
		if (preg_match('/(\\d{4})/', $season, $matches)) {
			$sort = ((int) $matches[1]) * 10;
		}
		return array(
			'key' => 'season-' . sanitize_key($season),
			'label' => sprintf(__('Seizoen %s', 'rokade-standings'), $season),
			'sort' => $sort,
		);
	}

	private function internal_group_rules() {
		if (null !== $this->group_rules) {
			return $this->group_rules;
		}

		$settings = $this->index->settings();
		$lines = preg_split('/\\r\\n|\\r|\\n/', (string) $settings['internal_group_order']);
		$rules = array();
		foreach ($lines as $line) {
			$parts = array_map('trim', explode('|', $line, 2));
			$label = isset($parts[1]) ? $parts[1] : $parts[0];
			$needle = $this->normalize_internal_group_name($parts[0]);
			if ('' !== $needle) {
				$rules[] = array('needle' => $needle, 'label' => $label);
			}
		}
		$this->group_rules = $rules;
		return $this->group_rules;
	}

	private function normalize_internal_group_name($value) {
		$normalized = strtolower(remove_accents($value));
		$normalized = str_replace(array('groep', 'meesters-/'), array('', 'meester-/'), $normalized);
		return trim(preg_replace('/\\s+/', ' ', $normalized));
	}

	/** Resolves a title to its configured button label and sort position in one pass. */
	private function match_internal_group($title) {
		$rules = $this->internal_group_rules();
		$normalized = $this->normalize_internal_group_name($title);
		foreach ($rules as $position => $rule) {
			if (false !== strpos($normalized, $rule['needle'])) {
				return array(
					'label' => '' !== $rule['label'] ? $rule['label'] : $title,
					'position' => $position,
				);
			}
		}
		return array('label' => $title, 'position' => count($rules));
	}

	private function sort_internal_items($a, $b) {
		$order = $a['group_position'] <=> $b['group_position'];
		return $order ?: ($a['number'] <=> $b['number']);
	}

	private function render_view_buttons($competition, $content_id) {
		$views = array(
			array('label' => __('Ranglijst', 'rokade-standings'), 'file' => $competition['ranking_file'], 'compact' => false),
			array('label' => __('Kruistabel', 'rokade-standings'), 'file' => $competition['cross_file'], 'compact' => true),
			array('label' => __('Scoretabel', 'rokade-standings'), 'file' => $competition['score_file'], 'compact' => true),
		);
		$output = '';
		foreach ($views as $view) {
			if (!$view['file']) {
				continue;
			}
			$active = $view['file'] === $competition['ranking_file'];
			$output .= '<button type="button" class="rokade-standings__view' . ($active ? ' is-active' : '') . '" data-file="' . esc_attr($view['file']) . '" data-compact="' . ($view['compact'] ? 'true' : 'false') . '" aria-pressed="' . ($active ? 'true' : 'false') . '" aria-controls="' . esc_attr($content_id) . '">' . esc_html($view['label']) . '</button>';
		}
		return $output;
	}

	private function render_file($source, $season, $relative_file, $iframe) {
		$url = $this->file_url($source, $season, $relative_file);
		if ($iframe) {
			return '<iframe class="rokade-standings__frame" title="' . esc_attr__('Standen', 'rokade-standings') . '" src="' . esc_url($url) . '" loading="lazy" sandbox="allow-same-origin"></iframe>';
		}

		$contents = $this->get_file_contents($source, $season, $relative_file);
		if (null === $contents) {
			return '<p class="rokade-standings__notice">' . esc_html__('Dit standenbestand kan niet worden gelezen.', 'rokade-standings') . '</p>';
		}

		return '<div class="rokade-standings__embedded">' . $this->sanitize_and_rewrite_html($source, $season, dirname($relative_file), $contents) . '</div>';
	}

	public function serve_source_file() {
		if (!isset($_GET['rokade_standings_file'], $_GET['rokade_standings_season'])) {
			return;
		}
		// Pages rendered before the plugin knew about multiple sources link
		// without one; those installs only ever had a single source anyway.
		$requested_source = isset($_GET['rokade_standings_source']) ? wp_unslash($_GET['rokade_standings_source']) : '';
		$source = is_scalar($requested_source) && '' !== $requested_source
			? sanitize_title((string) $requested_source)
			: $this->index->default_source_id();
		// A crafted query can hand over arrays (file[]=...); those are never a
		// valid request, so answer 404 rather than lean on how the sanitizers
		// happen to treat non-strings.
		$season = wp_unslash($_GET['rokade_standings_season']);
		$file = wp_unslash($_GET['rokade_standings_file']);
		if (!is_scalar($season) || !is_scalar($file)) {
			status_header(404);
			exit;
		}
		$season = sanitize_text_field((string) $season);
		$file = sanitize_text_field((string) $file);
		$contents = $this->get_file_contents($source, $season, $file);
		if (null === $contents) {
			status_header(404);
			exit;
		}

		nocache_headers();
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		echo $this->sanitize_and_rewrite_html($source, $season, dirname($file), $contents); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function file_url($source, $season, $file) {
		return add_query_arg(array(
			'rokade_standings_source' => $source,
			'rokade_standings_season' => $season,
			'rokade_standings_file' => $file,
		), home_url('/'));
	}

	private function get_file_contents($source, $season, $relative_file) {
		if (basename($season) !== $season || false !== strpos($season, "\0") || !preg_match('/^[A-Za-z0-9_ .\/()-]+\.html?$/i', $relative_file)) {
			return null;
		}
		$index = $this->index->get_index();
		if (!isset($index['sources'][$source]['seasons'][$season])) {
			return null;
		}
		// Against the configured path, not the cached one: a source removed from
		// the settings must stop serving files before the transient expires.
		// Check for the empty path explicitly: realpath('') answers the working
		// directory, which would quietly turn that removed source into the web root.
		$source_path = $this->index->source_path($source);
		if ('' === $source_path) {
			return null;
		}
		$root = realpath($source_path);
		$path = realpath($root . DIRECTORY_SEPARATOR . $season . DIRECTORY_SEPARATOR . $relative_file);
		$season_root = realpath($root . DIRECTORY_SEPARATOR . $season);
		if (!$root || !$season_root || !$path || 0 !== strpos($path, $season_root . DIRECTORY_SEPARATOR) || !is_readable($path)) {
			return null;
		}
		$contents = @file_get_contents($path);
		return false === $contents ? null : $contents;
	}

	private function sanitize_and_rewrite_html($source, $season, $relative_directory, $html) {
		// The caller passes dirname(), which is '.' for a file in the season root.
		$relative_directory = '.' === $relative_directory ? '' : $relative_directory;
		$html = $this->index->to_utf8($html);
		// preg_* return null when PCRE gives up (a big cross table can get there);
		// keep the previous stage rather than silently rendering nothing.
		$stripped = preg_replace('/<!doctype[^>]*>|<\/?(?:html|head|body)[^>]*>|<meta[^>]*>|<title[^>]*>.*?<\/title>|<style[^>]*>.*?<\/style>|<script[^>]*>.*?<\/script>/is', '', $html);
		$html = null === $stripped ? $html : $stripped;
		$rewritten = preg_replace_callback('/\b(href|src)\s*=\s*(["\'])([^"\']+)\2/i', function ($matches) use ($source, $season, $relative_directory) {
			$target = html_entity_decode($matches[3], ENT_QUOTES, 'UTF-8');
			if (preg_match('#^(?:https?:|mailto:|tel:|\#|/)#i', $target)) {
				return $matches[0];
			}
			$relative = ltrim($relative_directory . '/' . $target, '/');
			return $matches[1] . '=' . $matches[2] . esc_url($this->file_url($source, $season, $relative)) . $matches[2];
		}, $html);
		$html = null === $rewritten ? $html : $rewritten;

		// Merge onto the post defaults; assigning would drop scope/headers/abbr on
		// table cells and rel on links, which is exactly what keeps a standings
		// table readable in a screen reader.
		$common = array('class' => true, 'style' => true, 'align' => true);
		$extra = array(
			'table' => $common + array('border' => true, 'cellspacing' => true, 'cellpadding' => true),
			'thead' => $common,
			'tbody' => $common,
			'tr' => $common,
			'td' => $common + array('colspan' => true, 'rowspan' => true),
			'th' => $common + array('colspan' => true, 'rowspan' => true, 'scope' => true),
			'font' => array('class' => true, 'size' => true, 'color' => true, 'face' => true),
			'div' => $common,
			'span' => $common,
			'p' => $common,
			'br' => array('class' => true),
			'a' => $common + array('href' => true, 'title' => true, 'rel' => true),
			'b' => $common,
			'i' => $common,
			'strong' => $common,
			'em' => $common,
		);

		$allowed = wp_kses_allowed_html('post');
		foreach ($extra as $tag => $attributes) {
			$allowed[$tag] = isset($allowed[$tag]) ? array_merge($allowed[$tag], $attributes) : $attributes;
		}
		// Every link is rewritten to our own endpoint and handled inline by the
		// script; a stray target from a legacy export would open a bare fragment
		// in a new tab instead, without rel="noopener".
		unset($allowed['a']['target']);
		return wp_kses($html, $allowed);
	}
}
