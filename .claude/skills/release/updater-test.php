<?php
/**
 * Loopt de updater langs elk antwoord dat de feed kan geven. Draaien met:
 *
 *   docker compose run --rm wpcli wp eval-file \
 *     wp-content/plugins/rokade-standings/.claude/skills/release/updater-test.php
 *
 * De feed wordt met `pre_http_request` nagebootst, dus dit werkt ook zolang de
 * repository privé is. Alleen scenario 1 gaat echt het netwerk op.
 */

if (!defined('ABSPATH')) {
	exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/update.php';

$file = plugin_basename(ROKADE_STANDINGS_FILE);
$plugins = get_plugins();
if (!isset($plugins[$file])) {
	exit("De plugin staat niet in get_plugins(); draait dit wel tegen de lokale Docker-WordPress?\n");
}
$data = $plugins[$file];
$feed_url = Rokade_Standings_Updater::feed_url();

$manifest_file = ROKADE_STANDINGS_DIR . 'dist/update.json';
if (!file_exists($manifest_file)) {
	exit("Geen dist/update.json. Draai eerst `npm run package && npm run manifest -- --output dist/update.json`.\n");
}
$manifest = json_decode(file_get_contents($manifest_file), true);

$failures = 0;
$check = function ($name, $expectation, $passed) use (&$failures) {
	$failures += $passed ? 0 : 1;
	printf("%s  %-46s %s\n", $passed ? 'OK  ' : 'FOUT', $name, $expectation);
};

/** Voert één scenario uit met een nagebootst antwoord op de feed-URL. */
$with_feed = function ($response, callable $body) use ($feed_url) {
	$filter = function ($pre, $args, $url) use ($feed_url, $response) {
		if ($url !== $feed_url) {
			return $pre;
		}
		return is_wp_error($response) ? $response : array(
			'headers' => array(), 'cookies' => array(), 'filename' => null,
			'response' => array('code' => $response['code'], 'message' => 'x'),
			'body' => $response['body'],
		);
	};
	add_filter('pre_http_request', $filter, 10, 3);
	delete_transient('rokade_standings_update');
	delete_site_transient('update_plugins');
	$result = $body();
	remove_filter('pre_http_request', $filter, 10);
	delete_transient('rokade_standings_update');
	delete_site_transient('update_plugins');
	return $result;
};

$http = function ($body, $code = 200) {
	return array('body' => is_array($body) ? wp_json_encode($body) : $body, 'code' => $code);
};
$ask = function () use ($data, $file) {
	return Rokade_Standings_Updater::update(false, $data, $file);
};

echo "Feed: {$feed_url}\n\n";

// 1. Het echte netwerk. Privé repo -> 404, openbaar -> een geldige feed.
delete_transient('rokade_standings_update');
$live = $ask();
$check('echte feed', is_array($live) ? 'bereikbaar, versie ' . $live['version'] : 'onbereikbaar -> geen update (ook goed)', true);
$check('mislukking wordt onthouden', 'transient markeert de storing', is_array($live) || 'unavailable' === get_transient('rokade_standings_update'));
delete_transient('rokade_standings_update');

// 2. Een nieuwere versie moet als update bij WordPress terechtkomen.
$newer = $manifest;
$newer['version'] = '9.9.9';
$newer['package'] = 'https://github.com/jvanoostveen/rokade-standings/releases/download/v9.9.9/rokade-standings-9.9.9.zip';
$offered = $with_feed($http($newer), function () use ($file) {
	wp_update_plugins();
	$updates = get_site_transient('update_plugins');
	$details = plugins_api('plugin_information', array('slug' => 'rokade-standings'));
	return array(
		'response' => isset($updates->response[$file]) ? $updates->response[$file]->new_version : null,
		'details' => $details instanceof WP_Error ? null : $details->version,
		'changelog' => $details instanceof WP_Error ? 0 : strlen($details->sections['changelog']),
	);
});
$check('nieuwere versie', 'wp_update_plugins() biedt 9.9.9 aan', '9.9.9' === $offered['response']);
$check('details-modal', 'plugins_api levert de changelog', '9.9.9' === $offered['details'] && $offered['changelog'] > 0);

// 3. Dezelfde versie: geen update, wel no_update zodat auto-update aan kan.
$same = $with_feed($http($manifest), function () use ($file) {
	wp_update_plugins();
	$updates = get_site_transient('update_plugins');
	return array(
		'response' => isset($updates->response[$file]),
		'no_update' => isset($updates->no_update[$file]),
	);
});
$check('gelijke versie', 'geen update, wel no_update', !$same['response'] && $same['no_update']);

// 4. Alles wat mis kan gaan moet op false uitkomen, zonder notice of fatal.
$evil = $manifest; $evil['package'] = 'https://kwaadaardig.example.com/x.zip';
$weird = $manifest; $weird['version'] = '../../etc/passwd';
$broken = array(
	'HTTP 500' => $http('Server Error', 500),
	'HTTP 404' => $http('Not Found', 404),
	'geen JSON' => $http('<html>404</html>'),
	'JSON zonder versie' => $http(array('slug' => 'rokade-standings')),
	'JSON zonder pakket' => $http(array('version' => '9.9.9')),
	'pakket op vreemde host' => $http($evil),
	'onzinnig versienummer' => $http($weird),
	'netwerkfout' => new WP_Error('http_request_failed', 'geen verbinding'),
);
foreach ($broken as $name => $response) {
	$result = $with_feed($response, $ask);
	$check($name, 'levert geen update op', false === $result);
}

// 5. De filter hangt aan elke plugin met een github.com-Update-URI.
$check('andere github.com-plugin', 'blijft onaangeroerd', false === Rokade_Standings_Updater::update(false, $data, 'iets-anders/iets-anders.php'));

delete_transient('rokade_standings_update');
delete_site_transient('update_plugins');

echo "\n" . (0 === $failures ? "Alles in orde.\n" : "{$failures} controle(s) mislukt.\n");
