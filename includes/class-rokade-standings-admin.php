<?php

if (!defined('ABSPATH')) {
	exit;
}

class Rokade_Standings_Admin {
	private static $index = null;

	private static function index() {
		if (null === self::$index) {
			self::$index = new Rokade_Standings_Index();
		}
		return self::$index;
	}

	public static function register() {
		add_action('admin_menu', array(__CLASS__, 'menu'));
		add_action('admin_init', array(__CLASS__, 'upgrade_settings'));
		add_action('admin_init', array(__CLASS__, 'settings'));
		add_action('admin_post_rokade_standings_refresh', array(__CLASS__, 'refresh'));
		add_action('update_option_rokade_standings_settings', function () {
			self::index()->clear();
			// The cron recurrence is derived from the cache duration, so it has to
			// be re-registered whenever that duration changes.
			rokade_standings_schedule_refresh();
		});
	}

	/**
	 * Brings a stored option up to the current shape and name. The settings
	 * reader already understands both old forms (the earlier option name and the
	 * single bare source path), so this only keeps the database from carrying
	 * keys nothing writes any more, and drops the cron event and transient that
	 * were registered under the earlier name.
	 */
	public static function upgrade_settings() {
		$stored = get_option('rokade_standings_settings');
		$legacy = get_option(Rokade_Standings_Index::LEGACY_OPTION);
		$renames = !is_array($stored) && is_array($legacy);
		$reshapes = is_array($stored) && array_key_exists('source_path', $stored) && !isset($stored['sources']);
		if (!$renames && !$reshapes) {
			return;
		}

		update_option('rokade_standings_settings', self::sanitize(self::index()->settings()));
		if (is_array($legacy)) {
			delete_option(Rokade_Standings_Index::LEGACY_OPTION);
			delete_transient('schaken_standen_index_v3');
			wp_clear_scheduled_hook('schaken_standen_refresh_index');
		}
	}

	public static function menu() {
		add_options_page(__('Rokade Standen', 'rokade-standings'), __('Rokade Standen', 'rokade-standings'), 'manage_options', 'rokade-standings', array(__CLASS__, 'page'));
	}

	public static function settings() {
		register_setting('rokade_standings', 'rokade_standings_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize')));
		add_settings_section('rokade_standings_source', __('Bronnen en cache', 'rokade-standings'), function () {
			echo '<p>' . esc_html__('Een bronpad is de map waar de seizoensmappen (bijvoorbeeld 2026-2027) in staan. Geef er zoveel op als nodig; elke bron is apart te kiezen in het blok en de shortcode.', 'rokade-standings') . '</p>';
		}, 'rokade-standings');
		add_settings_field('sources', __('Bronpaden op de server', 'rokade-standings'), array(__CLASS__, 'sources_field'), 'rokade-standings', 'rokade_standings_source');
		add_settings_field('cache_minutes', __('Cacheduur (minuten)', 'rokade-standings'), array(__CLASS__, 'cache_field'), 'rokade-standings', 'rokade_standings_source');
		add_settings_section('rokade_standings_internal', __('Indeling interne competitie', 'rokade-standings'), function () {
			echo '<p>' . esc_html__('De periode wordt automatisch uit de titel herkend (bijvoorbeeld Voorjaar 2026). Benoem hieronder alleen de groepen, in de gewenste knopvolgorde.', 'rokade-standings') . '</p>';
		}, 'rokade-standings');
		add_settings_field('internal_group_order', __('Groepen en volgorde', 'rokade-standings'), array(__CLASS__, 'internal_group_order_field'), 'rokade-standings', 'rokade_standings_internal');
		add_settings_section('rokade_standings_blocks', __('Knopnamen voor blokcompetities', 'rokade-standings'), function () {
			echo '<p>' . esc_html__('Pas de labels voor doorgeefschaak en snelschaken aan zonder de exportbestanden te wijzigen.', 'rokade-standings') . '</p>';
		}, 'rokade-standings');
		add_settings_field('block_button_templates', __('Blokknoppen', 'rokade-standings'), array(__CLASS__, 'block_button_templates_field'), 'rokade-standings', 'rokade_standings_blocks');
		add_settings_section('rokade_standings_advanced', __('Geavanceerd', 'rokade-standings'), '__return_false', 'rokade-standings');
		add_settings_field('source_root', __('Hoofdmap van de bronnen', 'rokade-standings'), array(__CLASS__, 'source_root_field'), 'rokade-standings', 'rokade_standings_advanced');
	}

	public static function sanitize($input) {
		if (!is_array($input)) {
			$input = array();
		}

		return array(
			'sources' => sanitize_textarea_field($input['sources'] ?? ''),
			'source_root' => trim(sanitize_text_field($input['source_root'] ?? '')),
			'cache_minutes' => min(1440, max(1, absint($input['cache_minutes'] ?? 15))),
			'internal_group_order' => sanitize_textarea_field($input['internal_group_order'] ?? ''),
			'block_button_templates' => sanitize_textarea_field($input['block_button_templates'] ?? ''),
		);
	}

	public static function sources_field() {
		$settings = self::index()->settings();
		printf('<textarea class="large-text code" rows="4" name="rokade_standings_settings[sources]" placeholder="/wp-content/uploads/standen | Jeugd">%s</textarea>', esc_textarea($settings['sources']));
		echo '<p class="description">' . esc_html__('Eén bron per regel, in de vorm “pad | label”. Zonder label wordt de mapnaam gebruikt. Het label bepaalt ook de naam waarmee de bron in de shortcode wordt gekozen, bijvoorbeeld “Jeugd” wordt bron="jeugd".', 'rokade-standings') . '</p>';
		echo '<p class="description">' . esc_html(sprintf(__('Het pad telt vanaf de hoofdmap, nu %s. “/standen” is dus de map standen daarin.', 'rokade-standings'), self::index()->source_root() ?: '/')) . '</p>';
		echo '<p class="description">' . esc_html__('Let op: elk .htm- of .html-bestand onder zo’n pad wordt zonder inloggen openbaar leesbaar via de site. Wijs dus precies een standenmap aan en niets erboven.', 'rokade-standings') . '</p>';
	}

	public static function source_root_field() {
		$settings = self::index()->settings();
		printf('<input type="text" class="regular-text code" name="rokade_standings_settings[source_root]" value="%s" placeholder="%s">', esc_attr($settings['source_root']), esc_attr(untrailingslashit(ABSPATH)));
		echo '<p class="description">' . esc_html__('De map waar alle bronpaden vanaf tellen. Leeg laten gebruikt de WordPress-map. Staan de exports buiten de site, vul dan die map in, of “/” om volledige serverpaden te gebruiken.', 'rokade-standings') . '</p>';
	}

	public static function cache_field() {
		$settings = self::index()->settings();
		printf('<input type="number" min="1" max="1440" name="rokade_standings_settings[cache_minutes]" value="%d">', absint($settings['cache_minutes']));
	}

	public static function internal_group_order_field() {
		$settings = self::index()->settings();
		printf('<textarea class="large-text code" rows="9" name="rokade_standings_settings[internal_group_order]">%s</textarea>', esc_textarea($settings['internal_group_order']));
		echo '<p class="description">' . esc_html__('Eén groep per regel. Deze tekst wordt ook de knopnaam. De plugin negeert automatisch “groep” in de bestandsnaam; “Starters” herkent dus bijvoorbeeld “Startersgroep Voorjaar 2026”. Niet-herkende groepen blijven zichtbaar na deze lijst.', 'rokade-standings') . '</p>';
	}

	public static function block_button_templates_field() {
		$settings = self::index()->settings();
		printf('<textarea class="large-text code" rows="3" name="rokade_standings_settings[block_button_templates]">%s</textarea>', esc_textarea($settings['block_button_templates']));
		echo '<p class="description">' . esc_html__('Eén regel per categorie, in de vorm “categorie | knopnaam”. Gebruik {nummer} voor het bloknummer uit de titel, bijvoorbeeld “snelschaken | Blok {nummer}”.', 'rokade-standings') . '</p>';
	}

	public static function refresh() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Geen toegang.', 'rokade-standings'));
		}
		check_admin_referer('rokade_standings_refresh');
		// refresh() rescans and overwrites the transient, so clearing it first
		// only widens the window in which a visitor pays for the rescan.
		self::index()->refresh();
		wp_safe_redirect(add_query_arg('rokade_standings_refreshed', '1', admin_url('options-general.php?page=rokade-standings')));
		exit;
	}

	public static function page() {
		if (!current_user_can('manage_options')) {
			return;
		}
		$index = self::index()->get_index();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Rokade Standen', 'rokade-standings'); ?></h1>
			<?php if (isset($_GET['rokade_standings_refreshed'])) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Index vernieuwd.', 'rokade-standings'); ?></p></div><?php endif; ?>
			<form action="options.php" method="post">
				<?php settings_fields('rokade_standings'); do_settings_sections('rokade-standings'); submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e('Index verversen', 'rokade-standings'); ?></h2>
			<?php if (!$index['sources']) : ?>
				<p><?php esc_html_e('Er zijn nog geen bronpaden ingesteld.', 'rokade-standings'); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ($index['sources'] as $source_id => $source) : ?>
						<?php $found = count($source['seasons']); ?>
						<li>
							<strong><?php echo esc_html($source['label']); ?></strong>
							<code><?php echo esc_html($source_id); ?></code> &mdash;
							<?php echo esc_html(sprintf(_n('%d seizoen gevonden', '%d seizoenen gevonden', $found, 'rokade-standings'), $found)); ?>
							<?php if (!$found) : ?>
								<?php echo esc_html(sprintf(__('(niets leesbaar onder %s)', 'rokade-standings'), $source['path'])); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
				<input type="hidden" name="action" value="rokade_standings_refresh">
				<?php wp_nonce_field('rokade_standings_refresh'); submit_button(__('Nu opnieuw indexeren', 'rokade-standings'), 'secondary', 'submit', false); ?>
			</form>
			<hr>
			<h2><?php esc_html_e('Blok-editor', 'rokade-standings'); ?></h2>
			<p><?php esc_html_e('In de Gutenberg-editor is het blok “Rokade standen” beschikbaar. Kies daar bron, seizoen, competitie en weergave via dropdowns in de blokzijbalk.', 'rokade-standings'); ?></p>
			<hr>
			<h2><?php esc_html_e('Shortcode', 'rokade-standings'); ?></h2>
			<p><code>[rokade bron="jeugd" seizoen="2026-2027"]</code></p>
			<p><?php esc_html_e('Zonder bron wordt de eerste ingestelde bron gebruikt. Optioneel: categorie="interne-competitie", categorie="doorgeefschaak" of categorie="snelschaken". Gebruik modus="iframe" voor de oorspronkelijke Rokade-weergave.', 'rokade-standings'); ?></p>
		</div>
		<?php
	}
}
