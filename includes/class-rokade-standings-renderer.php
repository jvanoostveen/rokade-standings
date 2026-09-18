<?php

if (!defined('ABSPATH')) {
	exit;
}

class Schaken_Standen_Renderer {
	private $index;

	public function __construct($index) {
		$this->index = $index;
	}

	public function register() {
		add_shortcode('rokade', array($this, 'shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('template_redirect', array($this, 'serve_source_file'));
	}

	public function register_assets() {
		wp_register_style('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/rokade-standings.css', array(), SCHAKEN_STANDEN_VERSION);
		wp_register_script('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/rokade-standings.js', array(), SCHAKEN_STANDEN_VERSION, true);
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
		return $this->render($season, $competitions, 'iframe' === $attributes['modus']);
	}

	private function choose_season($seasons, $requested) {
		if ($requested) {
			return isset($seasons[$requested]) ? $requested : '';
		}
		$available = array_keys($seasons);
		return $available ? end($available) : '';
	}

	private function render($season, $competitions, $iframe) {
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
			<nav class="schaken-standen__categories" aria-label="<?php esc_attr_e('Soort competitie', 'schaken-standen'); ?>">
				<?php foreach ($categories as $key => $category) : ?>
					<button type="button" class="schaken-standen__category<?php echo $key === $first_category ? ' is-active' : ''; ?>" data-category="<?php echo esc_attr($key); ?>"><?php echo esc_html($category['label']); ?></button>
				<?php endforeach; ?>
			</nav>
			<?php foreach ($categories as $key => $category) : ?>
				<div class="schaken-standen__group<?php echo $key === $first_category ? ' is-active' : ''; ?>" data-category-panel="<?php echo esc_attr($key); ?>">
					<?php $tab_number = 0; ?>
					<?php foreach ($category['periods'] as $period) : ?>
						<section class="schaken-standen__period">
							<?php if ($period['label']) : ?><h3 class="schaken-standen__period-title"><?php echo esc_html($period['label']); ?></h3><?php endif; ?>
							<div class="schaken-standen__tabs" role="tablist" aria-label="<?php echo esc_attr($period['label'] ? $period['label'] : $category['label']); ?>">
								<?php foreach ($period['items'] as $competition) : ?>
									<?php $is_active = 0 === $tab_number++; ?>
									<button type="button" role="tab" class="schaken-standen__tab<?php echo $is_active ? ' is-active' : ''; ?>" data-file="<?php echo esc_attr($competition['ranking_file']); ?>" data-cross-file="<?php echo esc_attr($competition['cross_file']); ?>" data-score-file="<?php echo esc_attr($competition['score_file']); ?>" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"><?php echo esc_html(isset($competition['display_title']) ? $competition['display_title'] : $competition['title']); ?></button>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
					<div class="schaken-standen__views">
						<?php if ($key === $first_category) : ?>
							<?php echo $this->render_view_buttons($first_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
					<div class="schaken-standen__content" aria-live="polite">
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
			$item['display_title'] = $this->internal_display_title($item['title']);
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
		return $rules;
	}

	private function normalize_internal_group_name($value) {
		$normalized = strtolower(remove_accents($value));
		$normalized = str_replace(array('groep', 'meesters-/'), array('', 'meester-/'), $normalized);
		return trim(preg_replace('/\\s+/', ' ', $normalized));
	}

	private function internal_display_title($title) {
		foreach ($this->internal_group_rules() as $rule) {
			if (false !== strpos($this->normalize_internal_group_name($title), $rule['needle']) && '' !== $rule['label']) {
				return $rule['label'];
			}
		}
		return $title;
	}

	private function sort_internal_items($a, $b) {
		$rules = $this->internal_group_rules();
		$position_for = function ($title) use ($rules) {
			$normalized = $this->normalize_internal_group_name($title);
			foreach ($rules as $position => $rule) {
				if (false !== strpos($normalized, $rule['needle'])) {
					return $position;
				}
			}
			return count($rules);
		};
		$order = $position_for($a['title']) <=> $position_for($b['title']);
		return $order ?: ($a['number'] <=> $b['number']);
	}

	private function render_view_buttons($competition) {
		$views = array(
			array('label' => __('Ranglijst', 'schaken-standen'), 'file' => $competition['ranking_file']),
			array('label' => __('Kruistabel', 'schaken-standen'), 'file' => $competition['cross_file']),
			array('label' => __('Scoretabel', 'schaken-standen'), 'file' => $competition['score_file']),
		);
		$output = '';
		foreach ($views as $view) {
			if (!$view['file']) {
				continue;
			}
			$output .= '<button type="button" class="schaken-standen__view' . ($view['file'] === $competition['ranking_file'] ? ' is-active' : '') . '" data-file="' . esc_attr($view['file']) . '">' . esc_html($view['label']) . '</button>';
		}
		return $output;
	}

	private function render_file($season, $relative_file, $iframe) {
		$url = $this->file_url($season, $relative_file);
		if ($iframe) {
			return '<iframe class="schaken-standen__frame" title="' . esc_attr__('Standen', 'schaken-standen') . '" src="' . esc_url($url) . '" loading="lazy"></iframe>';
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
		header('Content-Type: text/html; charset=ISO-8859-1');
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
		$html = preg_replace('/<!doctype[^>]*>|<\/?(?:html|head|body)[^>]*>|<meta[^>]*>|<title[^>]*>.*?<\/title>|<style[^>]*>.*?<\/style>|<script[^>]*>.*?<\/script>/is', '', $html);
		$html = preg_replace_callback('/\b(href|src)\s*=\s*(["\'])([^"\']+)\2/i', function ($matches) use ($season, $relative_directory) {
			$target = html_entity_decode($matches[3], ENT_QUOTES, 'ISO-8859-1');
			if (preg_match('#^(?:https?:|mailto:|tel:|\#|/)#i', $target)) {
				return $matches[0];
			}
			$relative = ltrim($relative_directory . '/' . $target, '/');
			return $matches[1] . '=' . $matches[2] . esc_url($this->file_url($season, $relative)) . $matches[2];
		}, $html);

		$allowed = wp_kses_allowed_html('post');
		foreach (array('table', 'thead', 'tbody', 'tr', 'td', 'th', 'font', 'div', 'span', 'p', 'br', 'a', 'b', 'i', 'strong', 'em') as $tag) {
			$allowed[$tag] = array('class' => true, 'style' => true, 'align' => true, 'border' => true, 'cellspacing' => true, 'colspan' => true, 'rowspan' => true, 'href' => true, 'target' => true, 'size' => true);
		}
		return wp_kses($html, $allowed);
	}
}
