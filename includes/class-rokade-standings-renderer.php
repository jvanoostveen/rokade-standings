<?php

if (!defined('ABSPATH')) {
	exit;
}

class Schaken_Standen_Renderer {
	private $index;
	private $group_rules = null;

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
		wp_register_style('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/rokade-standings.css', array(), SCHAKEN_STANDEN_VERSION);
		wp_register_script('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/rokade-standings.js', array(), SCHAKEN_STANDEN_VERSION, true);
		// The same strings the server-rendered markup uses, so both stay in step once translated.
		wp_localize_script('schaken-standen', 'schakenStandenL10n', array(
			'ranking' => __('Ranglijst', 'schaken-standen'),
			'cross' => __('Kruistabel', 'schaken-standen'),
			'score' => __('Scoretabel', 'schaken-standen'),
			'back' => __('Terug naar ranglijst', 'schaken-standen'),
			'frameTitle' => __('Standen', 'schaken-standen'),
			'loadError' => __('Dit standenbestand kan niet worden geladen.', 'schaken-standen'),
		));
		wp_register_script(
			'schaken-standen-block-editor',
			SCHAKEN_STANDEN_URL . 'assets/rokade-standings-block.js',
			array('wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'),
			SCHAKEN_STANDEN_VERSION,
			true
		);
	}

	/** Registers the dynamic Gutenberg equivalent of the [rokade] shortcode. */
	public function register_block() {
		if (!function_exists('register_block_type')) {
			return;
		}

		// Everything but the callback lives in blocks/rokade/block.json, so the
		// editor can read the same definition the server registers.
		register_block_type(SCHAKEN_STANDEN_DIR . 'blocks/rokade', array(
			'render_callback' => array($this, 'render_block'),
		));
	}

	/** Loads index-derived dropdown choices only in the block editor. */
	public function localize_block_options() {
		wp_localize_script('schaken-standen-block-editor', 'schakenStandenBlock', $this->block_options());
	}

	/** Supplies editor dropdown options from the current, cached standings index. */
	private function block_options() {
		$data = $this->index->get_index();
		$season_categories = array();
		foreach ($data['seasons'] as $season => $competitions) {
			$categories = array();
			foreach ($competitions as $competition) {
				$categories[$competition['category']] = $competition['category_label'];
			}
			$season_categories[$season] = $categories;
		}

		return array(
			'seasons' => array_keys($data['seasons']),
			'seasonCategories' => $season_categories,
			'labels' => array(
				'latestSeason' => __('Meest recente seizoen', 'schaken-standen'),
				'allCategories' => __('Alle competities', 'schaken-standen'),
				'inline' => __('Inline (in de pagina)', 'schaken-standen'),
				'iframe' => __('Oorspronkelijke Rokade-weergave', 'schaken-standen'),
			),
		);
	}

	public function render_block($attributes) {
		return $this->shortcode(is_array($attributes) ? $attributes : array());
	}

	public function shortcode($attributes) {
		$attributes = shortcode_atts(array(
			'seizoen' => '',
			'categorie' => '',
			'modus' => 'inline',
		), $attributes, 'rokade');
		$data = $this->index->get_index();
		$season = $this->choose_season($data['seasons'], $attributes['seizoen']);

		if (!$season) {
			return current_user_can('manage_options')
				? '<p class="schaken-standen__notice">' . esc_html__('Er zijn geen leesbare standen gevonden. Stel het bronpad in onder Instellingen → Rokade Standen.', 'schaken-standen') . '</p>'
				: '';
		}

		$competitions = $data['seasons'][$season];
		if ($attributes['categorie']) {
			$competitions = array_values(array_filter($competitions, function ($competition) use ($attributes) {
				return $competition['category'] === sanitize_title($attributes['categorie']);
			}));
		}
		if (!$competitions) {
			return '';
		}

		wp_enqueue_style('schaken-standen');
		wp_enqueue_script('schaken-standen');
		return $this->render($season, $competitions, 'iframe' === $attributes['modus'], empty($attributes['categorie']));
	}

	private function choose_season($seasons, $requested) {
		if ($requested) {
			return isset($seasons[$requested]) ? $requested : '';
		}
		$available = array_keys($seasons);
		return $available ? end($available) : '';
	}

	private function render($season, $competitions, $iframe, $show_categories = true) {
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
		$first_category = array_key_first($categories);
		$first_item = $categories[$first_category]['periods'][0]['items'][0];
		$instance = 'schaken-standen-' . wp_generate_uuid4();

		ob_start();
		?>
		<section class="schaken-standen" id="<?php echo esc_attr($instance); ?>" data-mode="<?php echo $iframe ? 'iframe' : 'inline'; ?>" data-season="<?php echo esc_attr($season); ?>">
			<?php if ($show_categories) : ?>
				<div class="schaken-standen__categories" role="group" aria-label="<?php esc_attr_e('Soort competitie', 'schaken-standen'); ?>">
					<?php foreach ($categories as $key => $category) : ?>
						<button type="button" class="schaken-standen__category<?php echo $key === $first_category ? ' is-active' : ''; ?>" data-category="<?php echo esc_attr($key); ?>" aria-pressed="<?php echo $key === $first_category ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($instance . '-' . $key); ?>"><?php echo esc_html($category['label']); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php foreach ($categories as $key => $category) : ?>
				<?php $content_id = $instance . '-' . $key . '-content'; ?>
				<div class="schaken-standen__group<?php echo $key === $first_category ? ' is-active' : ''; ?>" id="<?php echo esc_attr($instance . '-' . $key); ?>" data-category-panel="<?php echo esc_attr($key); ?>">
					<?php $tab_number = 0; ?>
					<?php foreach ($category['periods'] as $period) : ?>
						<section class="schaken-standen__period">
							<?php if ($period['label']) : ?><h3 class="schaken-standen__period-title"><?php echo esc_html($period['label']); ?></h3><?php endif; ?>
							<div class="schaken-standen__tabs" role="group" aria-label="<?php echo esc_attr($period['label'] ? $period['label'] : $category['label']); ?>">
								<?php foreach ($period['items'] as $competition) : ?>
									<?php $is_active = 0 === $tab_number++; ?>
									<button type="button" class="schaken-standen__tab<?php echo $is_active ? ' is-active' : ''; ?>" data-file="<?php echo esc_attr($competition['ranking_file']); ?>" data-cross-file="<?php echo esc_attr($competition['cross_file']); ?>" data-score-file="<?php echo esc_attr($competition['score_file']); ?>" aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($content_id); ?>"><?php echo esc_html(isset($competition['display_title']) ? $competition['display_title'] : $competition['title']); ?></button>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
					<div class="schaken-standen__views" role="group" aria-label="<?php esc_attr_e('Weergave', 'schaken-standen'); ?>">
						<?php if ($key === $first_category) : ?>
							<?php echo $this->render_view_buttons($first_item, $content_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
					<div class="schaken-standen__content" id="<?php echo esc_attr($content_id); ?>" role="region" aria-label="<?php esc_attr_e('Standen', 'schaken-standen'); ?>" aria-live="polite">
						<?php if ($key === $first_category) : ?>
							<?php echo $this->render_file($season, $first_item['ranking_file'], $iframe); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private function category_display_title($category, $title) {
		if (!in_array($category, array('doorgeefschaak', 'snelschaken'), true) || !preg_match('/\\bblok\\s+(\\d+)\\b/ui', $title, $matches)) {
			return $title;
		}

		$settings = $this->index->settings();
		$lines = preg_split('/\\r\\n|\\r|\\n/', (string) $settings['block_button_templates']);
		foreach ($lines as $line) {
			$parts = array_map('trim', explode('|', $line, 2));
			if (2 === count($parts) && sanitize_title($parts[0]) === $category && '' !== $parts[1]) {
				return str_replace('{nummer}', (string) ((int) $matches[1]), $parts[1]);
			}
		}
		return $title;
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
			$label = ('voorjaar' === $season_part ? __('Voorjaar', 'schaken-standen') : __('Najaar', 'schaken-standen')) . ' ' . $year;
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
			'label' => sprintf(__('Seizoen %s', 'schaken-standen'), $season),
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
			array('label' => __('Ranglijst', 'schaken-standen'), 'file' => $competition['ranking_file'], 'compact' => false),
			array('label' => __('Kruistabel', 'schaken-standen'), 'file' => $competition['cross_file'], 'compact' => true),
			array('label' => __('Scoretabel', 'schaken-standen'), 'file' => $competition['score_file'], 'compact' => true),
		);
		$output = '';
		foreach ($views as $view) {
			if (!$view['file']) {
				continue;
			}
			$active = $view['file'] === $competition['ranking_file'];
			$output .= '<button type="button" class="schaken-standen__view' . ($active ? ' is-active' : '') . '" data-file="' . esc_attr($view['file']) . '" data-compact="' . ($view['compact'] ? 'true' : 'false') . '" aria-pressed="' . ($active ? 'true' : 'false') . '" aria-controls="' . esc_attr($content_id) . '">' . esc_html($view['label']) . '</button>';
		}
		return $output;
	}

	private function render_file($season, $relative_file, $iframe) {
		$url = $this->file_url($season, $relative_file);
		if ($iframe) {
			return '<iframe class="schaken-standen__frame" title="' . esc_attr__('Standen', 'schaken-standen') . '" src="' . esc_url($url) . '" loading="lazy" sandbox="allow-same-origin"></iframe>';
		}

		$contents = $this->get_file_contents($season, $relative_file);
		if (null === $contents) {
			return '<p class="schaken-standen__notice">' . esc_html__('Dit standenbestand kan niet worden gelezen.', 'schaken-standen') . '</p>';
		}

		return '<div class="schaken-standen__embedded">' . $this->sanitize_and_rewrite_html($season, dirname($relative_file), $contents) . '</div>';
	}

	public function serve_source_file() {
		if (!isset($_GET['schaken_standen_file'], $_GET['schaken_standen_season'])) {
			return;
		}
		$season = sanitize_text_field(wp_unslash($_GET['schaken_standen_season']));
		$file = sanitize_text_field(wp_unslash($_GET['schaken_standen_file']));
		$contents = $this->get_file_contents($season, $file);
		if (null === $contents) {
			status_header(404);
			exit;
		}

		nocache_headers();
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		echo $this->sanitize_and_rewrite_html($season, dirname($file), $contents); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function file_url($season, $file) {
		return add_query_arg(array(
			'schaken_standen_season' => $season,
			'schaken_standen_file' => $file,
		), home_url('/'));
	}

	private function get_file_contents($season, $relative_file) {
		if (basename($season) !== $season || false !== strpos($season, "\0") || !preg_match('/^[A-Za-z0-9_ .\/()-]+\.html?$/i', $relative_file)) {
			return null;
		}
		$known_seasons = $this->index->get_index();
		if (!isset($known_seasons['seasons'][$season])) {
			return null;
		}
		$root = realpath($this->index->source_path());
		$path = realpath($root . DIRECTORY_SEPARATOR . $season . DIRECTORY_SEPARATOR . $relative_file);
		$season_root = realpath($root . DIRECTORY_SEPARATOR . $season);
		if (!$root || !$season_root || !$path || 0 !== strpos($path, $season_root . DIRECTORY_SEPARATOR) || !is_readable($path)) {
			return null;
		}
		$contents = @file_get_contents($path);
		return false === $contents ? null : $contents;
	}

	private function sanitize_and_rewrite_html($season, $relative_directory, $html) {
		$html = $this->index->to_utf8($html);
		// preg_* return null when PCRE gives up (a big cross table can get there);
		// keep the previous stage rather than silently rendering nothing.
		$stripped = preg_replace('/<!doctype[^>]*>|<\/?(?:html|head|body)[^>]*>|<meta[^>]*>|<title[^>]*>.*?<\/title>|<style[^>]*>.*?<\/style>|<script[^>]*>.*?<\/script>/is', '', $html);
		$html = null === $stripped ? $html : $stripped;
		$rewritten = preg_replace_callback('/\b(href|src)\s*=\s*(["\'])([^"\']+)\2/i', function ($matches) use ($season, $relative_directory) {
			$target = html_entity_decode($matches[3], ENT_QUOTES, 'UTF-8');
			if (preg_match('#^(?:https?:|mailto:|tel:|\#|/)#i', $target)) {
				return $matches[0];
			}
			$relative = ltrim($relative_directory . '/' . $target, '/');
			return $matches[1] . '=' . $matches[2] . esc_url($this->file_url($season, $relative)) . $matches[2];
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
