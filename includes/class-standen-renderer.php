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
		add_shortcode('schaken_standen', array($this, 'shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('template_redirect', array($this, 'serve_source_file'));
	}

	public function register_assets() {
		wp_register_style('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/standen.css', array(), SCHAKEN_STANDEN_VERSION);
		wp_register_script('schaken-standen', SCHAKEN_STANDEN_URL . 'assets/standen.js', array(), SCHAKEN_STANDEN_VERSION, true);
	}

	public function shortcode($attributes) {
		$attributes = shortcode_atts(array(
			'seizoen' => '',
			'categorie' => '',
			'modus' => 'inline',
		), $attributes, 'schaken_standen');
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
		if ($requested && isset($seasons[$requested])) {
			return $requested;
		}
		$available = array_keys($seasons);
		return $available ? end($available) : '';
	}

	private function render($season, $competitions, $iframe) {
		$categories = array();
		foreach ($competitions as $competition) {
			$categories[$competition['category']]['label'] = $competition['category_label'];
			$categories[$competition['category']]['items'][] = $competition;
		}
		$first_category = array_key_first($categories);
		$first_item = $categories[$first_category]['items'][0];
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
					<div class="schaken-standen__tabs" role="tablist" aria-label="<?php echo esc_attr($category['label']); ?>">
						<?php foreach ($category['items'] as $position => $competition) : ?>
							<button type="button" role="tab" class="schaken-standen__tab<?php echo 0 === $position ? ' is-active' : ''; ?>" data-file="<?php echo esc_attr($competition['ranking_file']); ?>" aria-selected="<?php echo 0 === $position ? 'true' : 'false'; ?>"><?php echo esc_html($competition['title']); ?></button>
						<?php endforeach; ?>
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
		if (!preg_match('/^\d{4}-\d{4}$/', $season) || !preg_match('/^[A-Za-z0-9_ .\/()-]+\.html?$/i', $relative_file)) {
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
