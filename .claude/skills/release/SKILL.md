---
name: release
description: Breng een nieuwe versie van Rokade Standen uit en onderhoud de automatische updates. Gebruik dit bij het ophogen van het versienummer, bij een mislukte of overgeslagen Release-workflow, bij wijzigingen aan het pluginpakket, de update-feed of de updaterklasse, en wanneer een site geen update te zien krijgt.
---

# Een versie uitbrengen

Een release is één handeling: het versienummer ophogen en naar `main` pushen. De workflow
[`.github/workflows/release.yml`](../../../.github/workflows/release.yml) doet de rest. Bouw
of upload nooit met de hand een zip naar een release — dan loopt de feed uit de pas met de
tag.

```sh
# 1. Beide plekken ophogen; de workflow weigert als ze verschillen.
#    rokade-standings.php:  * Version: 0.3.0
#    rokade-standings.php:  define('ROKADE_STANDINGS_VERSION', '0.3.0');
# 2. Lokaal nalopen (zie hieronder).
# 3. Committen en pushen naar main.
```

Elke push naar `main` bouwt sowieso een artifact. Alleen een versie waarvoor nog geen tag
`v<versie>` bestaat, wordt ook als release gepubliceerd.

## Wat waar staat

| Bestand | Rol |
| --- | --- |
| `rokade-standings.php` | De waarheid over de versie: de `Version:`-header én `ROKADE_STANDINGS_VERSION`. Draagt ook de `Update URI:`-header die WordPress naar de updater stuurt. |
| `.github/workflows/release.yml` | Lint, bouw, controle, artifact, release. Draait op elke push naar `main` en handmatig. |
| `scripts/plugin-header.sh` | Leest één header-veld. De enige plek met een regex voor het versienummer. |
| `scripts/package.sh` | Bouwt `dist/rokade-standings-<versie>.zip`. Kopieert een **expliciete lijst**. |
| `scripts/release-manifest.sh` | Bouwt `update.json`: de feed. Geen jq, met de hand samengesteld. |
| `includes/class-rokade-standings-updater.php` | De kant van de plugin: leest de feed, vult de updatemelding en de details-modal. |
| `.claude/skills/release/updater-test.php` | Testharnas voor die klasse; zie *De updater testen*. |

De feed staat op de vaste URL die GitHub altijd naar de nieuwste release laat wijzen:
`https://github.com/jvanoostveen/rokade-standings/releases/latest/download/update.json`.

## Vooraf lokaal controleren

```sh
# Dezelfde poort als CI; geen uitvoer betekent dat alles parseert.
docker run --rm -v "$PWD":/app -w /app php:8.3-cli \
  bash -c 'find . -name "*.php" -exec php -l {} \; | grep -v "^No syntax errors"'
npm run package                                    # dist/rokade-standings-<versie>.zip
npm run manifest                                   # de feed naar stdout, om te lezen
```

PHP staat niet op de host; gebruik altijd een container. Vergelijk de uitkomst van
`npm run manifest` met wat je verwacht: `version`, `package` en de changelog.

`php -l` schrijft zijn parsefout naar stdout, niet naar stderr. Gooi die dus niet weg met
`>/dev/null` — dan blijft er alleen een nietszeggende `xargs`-melding over.

## De controles van de workflow

De job stopt met een `::error::` als een van deze klopt. De melding noemt telkens het
werkelijke getal, dus lees die eerst.

| Melding | Oorzaak |
| --- | --- |
| `ROKADE_STANDINGS_VERSION is X, maar de pluginheader zegt Y` | Maar één van de twee plekken opgehoogd. |
| `Versie X is lager dan de al gepubliceerde vY` | GitHub wijst "latest" toe aan de **laatst aangemaakte** release, dus een lagere versie zou elke site terugzetten. |
| `Feed of pakket klopt niet` | `update.json` en de zip horen niet bij elkaar, of een verwacht bestand ontbreekt in het archief. |
| `::notice:: … is al gereleased` | Geen fout: de tag bestaat al, er komt alleen een artifact. |

Een release overdoen betekent de tag én de release weghalen
(`gh release delete v0.3.0 --cleanup-tag`) en opnieuw pushen. Doe dat alleen zolang niemand
de versie kan hebben opgehaald; een site die al bijgewerkt is, ziet een vervangen release
nooit meer.

De releasetekst komt uit `--generate-notes`, de changelog in `update.json` uit de
commit-onderwerpen sinds de vorige tag. Die onderwerpen komen dus in beeld bij de beheerder
onder **Details bekijken** — schrijf ze zo.

## De updater testen

De lokale Docker-WordPress mount de hele repo, dus de harnas draait zonder kopiëren:

```sh
docker compose up -d
npm run package && npm run manifest -- --output dist/update.json
docker compose run --rm wpcli wp eval-file \
  wp-content/plugins/rokade-standings/.claude/skills/release/updater-test.php
```

Die loopt elk antwoord langs dat de feed kan geven — nieuwere versie, gelijke versie, 404,
500, geen JSON, pakket op een vreemde host, netwerkfout — en moet eindigen op
`Alles in orde.` Draai hem na elke wijziging in de updaterklasse of in het formaat van
`update.json`.

Het uitgangspunt: **een mislukte controle mag nooit een foutmelding, notice of trage
beheerpagina opleveren.** WordPress hoort dan simpelweg niets over een nieuwe versie. Alles
wat `parse()` niet vertrouwt, wordt `false`.

## Valkuilen

- **`package.sh` kopieert een expliciete lijst** (`assets`, `includes`, `blocks`, het
  pluginbestand, `uninstall.php`, `README.md`). Een nieuwe map op het hoogste niveau — denk
  aan `languages/` voor de vertalingen die `load_plugin_textdomain()` verwacht — valt er
  stilzwijgend buiten. De CI-controle kijkt maar naar twee bestanden en vangt dat niet.
- **`dist/` staat in `.gitignore`.** Nooit committen; de zip komt uit de release of het
  artifact.
- **De repo is nu privé, dus de feed geeft 404.** Dat is het verwachte gedrag, geen bug.
  Zodra de repo openbaar is, werkt de feed zonder wijziging in de plugin.
- **Bij verhuizen of hernoemen van de repository** moeten de `Update URI:`-header,
  `Rokade_Standings_Updater::REPOSITORY` en de URL's in de README allemaal mee. De workflow
  merkt het niet, want die haalt de repo uit `github.repository`.
- **`update.json` wordt met de hand in bash samengesteld.** Een nieuw veld met vrije tekst
  moet door `json_escape()`; HTML voor `sections` bovendien door `html_escape()`.
- **De updater cachet twaalf uur** (een mislukking een uur). Bij handmatig proberen eerst
  `wp eval 'delete_transient("rokade_standings_update");'`, of in het beheer
  **Opnieuw controleren**.
- **WordPress zelf controleert ongeveer twee keer per dag.** Een site ziet een nieuwe versie
  dus niet meteen; dat is niet kapot.
- **De scripts hebben het uitvoerbit nodig** (`git ls-files -s scripts/` moet `100755`
  tonen), anders struikelt de workflow erover.
