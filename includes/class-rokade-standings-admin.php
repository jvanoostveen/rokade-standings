<?php

if (!defined('ABSPATH')) {
	exit;
}

class Schaken_Standen_Admin {
	private static $index = null;

	private static function index() {
		if (null === self::$index) {
			self::$index = new Schaken_Standen_Index();
		}
		return self::$index;
	}

	public static function register() {
		add_action('admin_menu', array(__CLASS__, 'menu'));
		add_action('admin_init', array(__CLASS__, 'settings'));
		add_action('admin_post_schaken_standen_refresh', array(__CLASS__, 'refresh'));
		add_action('update_option_schaken_standen_settings', function () {
			self::index()->clear();
			// The cron recurrence is derived from the cache duration, so it has to
			// be re-registered whenever that duration changes.
			schaken_standen_schedule_refresh();
		});
	}

	public static function menu() {
		add_options_page(__('Rokade Standen', 'schaken-standen'), __('Rokade Standen', 'schaken-standen'), 'manage_options', 'schaken-standen', array(__CLASS__, 'page'));
	}

	public static function settings() {
		register_setting('schaken_standen', 'schaken_standen_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize')));
		add_settings_section('schaken_standen_source', __('Bron en cache', 'schaken-standen'), function () {
			echo '<p>' . esc_html__('Het bronpad is de map waar de seizoensmappen (bijvoorbeeld 2026-2027) in staan.', 'schaken-standen') . '</p>';
		}, 'schaken-standen');
		add_settings_field('source_path', __('Bronpad op de server', 'schaken-standen'), array(__CLASS__, 'source_path_field'), 'schaken-standen', 'schaken_standen_source');
		add_settings_field('cache_minutes', __('Cacheduur (minuten)', 'schaken-standen'), array(__CLASS__, 'cache_field'), 'schaken-standen', 'schaken_standen_source');
		add_settings_section('schaken_standen_internal', __('Indeling interne competitie', 'schaken-standen'), function () {
			echo '<p>' . esc_html__('De periode wordt automatisch uit de titel herkend (bijvoorbeeld Voorjaar 2026). Benoem hieronder alleen de groepen, in de gewenste knopvolgorde.', 'schaken-standen') . '</p>';
		}, 'schaken-standen');
		add_settings_field('internal_group_order', __('Groepen en volgorde', 'schaken-standen'), array(__CLASS__, 'internal_group_order_field'), 'schaken-standen', 'schaken_standen_internal');
		add_settings_section('schaken_standen_blocks', __('Knopnamen voor blokcompetities', 'schaken-standen'), function () {
			echo '<p>' . esc_html__('Pas de labels voor doorgeefschaak en snelschaken aan zonder de exportbestanden te wijzigen.', 'schaken-standen') . '</p>';
		}, 'schaken-standen');
		add_settings_field('block_button_templates', __('Blokknoppen', 'schaken-standen'), array(__CLASS__, 'block_button_templates_field'), 'schaken-standen', 'schaken_standen_blocks');
	}

	public static function sanitize($input) {
		if (!is_array($input)) {
			$input = array();
		}

		return array(
			'source_path' => untrailingslashit(sanitize_text_field($input['source_path'] ?? '')),
			'cache_minutes' => min(1440, max(1, absint($input['cache_minutes'] ?? 15))),
			'internal_group_order' => sanitize_textarea_field($input['internal_group_order'] ?? ''),
			'block_button_templates' => sanitize_textarea_field($input['block_button_templates'] ?? ''),
		);
	}

	public static function source_path_field() {
		$settings = self::index()->settings();
		printf('<input type="text" class="regular-text code" name="schaken_standen_settings[source_path]" value="%s" placeholder="/var/www/html/wp-content/uploads/standen">', esc_attr($settings['source_path']));
		echo '<p class="description">' . esc_html__('Let op: elk .htm- of .html-bestand onder dit pad wordt zonder inloggen openbaar leesbaar via de site. Wijs dus precies de standenmap aan en niets erboven.', 'schaken-standen') . '</p>';
	}

	public static function cache_field() {
		$settings = self::index()->settings();
		printf('<input type="number" min="1" max="1440" name="schaken_standen_settings[cache_minutes]" value="%d">', absint($settings['cache_minutes']));
	}

	public static function internal_group_order_field() {
		$settings = self::index()->settings();
		printf('<textarea class="large-text code" rows="9" name="schaken_standen_settings[internal_group_order]">%s</textarea>', esc_textarea($settings['internal_group_order']));
		echo '<p class="description">' . esc_html__('Eén groep per regel. Deze tekst wordt ook de knopnaam. De plugin negeert automatisch “groep” in de bestandsnaam; “Starters” herkent dus bijvoorbeeld “Startersgroep Voorjaar 2026”. Niet-herkende groepen blijven zichtbaar na deze lijst.', 'schaken-standen') . '</p>';
	}

	public static function block_button_templates_field() {
		$settings = self::index()->settings();
		printf('<textarea class="large-text code" rows="3" name="schaken_standen_settings[block_button_templates]">%s</textarea>', esc_textarea($settings['block_button_templates']));
		echo '<p class="description">' . esc_html__('Eén regel per categorie, in de vorm “categorie | knopnaam”. Gebruik {nummer} voor het bloknummer uit de titel, bijvoorbeeld “snelschaken | Blok {nummer}”.', 'schaken-standen') . '</p>';
	}

	public static function refresh() {
		check_admin_referer('schaken_standen_refresh');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Geen toegang.', 'schaken-standen'));
		}
		$index = self::index();
		$index->clear();
		$index->refresh();
		wp_safe_redirect(add_query_arg('schaken_standen_refreshed', '1', admin_url('options-general.php?page=schaken-standen')));
		exit;
	}

	public static function page() {
		if (!current_user_can('manage_options')) {
			return;
		}
		$index = self::index()->get_index();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Rokade Standen', 'schaken-standen'); ?></h1>
			<?php if (isset($_GET['schaken_standen_refreshed'])) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Index vernieuwd.', 'schaken-standen'); ?></p></div><?php endif; ?>
			<form action="options.php" method="post">
				<?php settings_fields('schaken_standen'); do_settings_sections('schaken-standen'); submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e('Index verversen', 'schaken-standen'); ?></h2>
			<p><?php echo esc_html(sprintf(_n('%d seizoen gevonden.', '%d seizoenen gevonden.', count($index['seasons']), 'schaken-standen'), count($index['seasons']))); ?></p>
			<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
				<input type="hidden" name="action" value="schaken_standen_refresh">
				<?php wp_nonce_field('schaken_standen_refresh'); submit_button(__('Nu opnieuw indexeren', 'schaken-standen'), 'secondary', 'submit', false); ?>
			</form>
			<hr>
			<h2><?php esc_html_e('Blok-editor', 'schaken-standen'); ?></h2>
			<p><?php esc_html_e('In de Gutenberg-editor is het blok “Rokade standen” beschikbaar. Kies daar seizoen, competitie en weergave via dropdowns in de blokzijbalk.', 'schaken-standen'); ?></p>
			<hr>
			<h2><?php esc_html_e('Shortcode', 'schaken-standen'); ?></h2>
			<p><code>[rokade seizoen="2026-2027"]</code></p>
			<p><?php esc_html_e('Optioneel: categorie="interne-competitie", categorie="doorgeefschaak" of categorie="snelschaken". Gebruik modus="iframe" voor de oorspronkelijke Rokade-weergave.', 'schaken-standen'); ?></p>
		</div>
		<?php
	}
}
