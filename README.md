# Rokade Standen

WordPress-plugin voor de HTML-standen die Rokade naar een map op de server schrijft. De plugin scant uitsluitend de kleine `C*Index.htm`-bestanden, zowel in een submap per competitie (`<seizoen>/<groep>/C1Index.htm`) als rechtstreeks in de seizoensmap (`<seizoen>/C1Index.htm`); exports gebruiken beide indelingen door elkaar. Hun `<title>` wordt de knoptekst; titels met “Doorgeef” en “Snelschaak” vormen automatisch die categorieën, de rest valt onder **Interne competitie**.

Er zijn meerdere bronpaden mogelijk, elk met een eigen label — bijvoorbeeld een aparte export voor de jeugd en voor de senioren. Elke bron wordt los geïndexeerd en is per blok of shortcode te kiezen.

De cache voorkomt dat bij elke paginaview de directory wordt doorzocht. De index is een WordPress transient met een instelbare levensduur (standaard 15 minuten), wordt via WP-Cron op de achtergrond opgewarmd voordat hij verloopt en kan via de instellingen onmiddellijk worden vernieuwd.

## Handleiding

Voor beheerders van de site staat er een handleiding met schermafbeeldingen en video in
[`docs/handleiding/`](docs/handleiding/README.md): deel 1 gaat over installeren en instellen,
deel 2 over het plaatsen van het blok op een pagina. De beelden worden opgenomen door het
scenario in [`tools/handleiding/`](tools/handleiding/README.md), zodat ze na een nieuwe
feature in één opdracht opnieuw te maken zijn.

## Lokaal starten met OrbStack/Docker

```sh
docker compose up -d
```

De geanonimiseerde demo-export staat al in `demo/`, dus een clone is direct klaar voor lokaal gebruik. Open <http://localhost:8080>, voltooi de WordPress-installatie en activeer **Rokade Standen**. Ga naar **Instellingen → Rokade Standen** en vul dit bronpad in:

```
/standen | Jeugd
```

De lokale demo-exportmappen worden in de container read-only gemount: `demo/jeugd` als `/standen` en `demo/senioren` als `/senioren`. Een export als `demo/jeugd/2026-2027/…` is in de container dus beschikbaar als `/standen/2026-2027/…`. Met twee bronnen wordt het veld:

```
/standen | Jeugd
/senioren | Senioren
```

Een extra bron toevoegen betekent een mountregel in `docker-compose.yml` en een regel in dit veld. De demo bevat alleen HTML en assets die de plugin nodig heeft; de spelersnamen zijn consistente pseudoniemen. Vernieuw die demo na een nieuwe export met `node tools/anonymize-demo.mjs` voordat je hem commit.

Maak vervolgens bijvoorbeeld een pagina met:

```
[rokade seizoen="2025-2026"]
```

Handige varianten:

```
[rokade bron="senioren" seizoen="2025-2026"]
[rokade seizoen="2026-2027" categorie="interne-competitie"]
[rokade seizoen="2025-2026" categorie="snelschaken"]
[rokade seizoen="2025-2026" modus="iframe"]
```

## Gutenberg-blok

Naast de shortcode is er in de blok-editor het blok **Rokade standen**. Selecteer het blok en kies in de zijbalk de bron, het seizoen, de competitie en de weergave (inline of de oorspronkelijke Rokade-weergave). De bronkeuze verschijnt zodra er meer dan één bronpad is ingesteld; seizoen en competitie horen bij de gekozen bron en worden leeggemaakt zodra ze daar niet bestaan. De opties worden samengesteld uit de actuele standenindex. Na het vernieuwen van die index zijn nieuwe seizoenen en competities ook in het blok beschikbaar.

Standaard is de modus `inline`: het ranglijstdeel wordt door de plugin veilig ingelezen en krijgt de lettertypes, kleuren en links van het WordPress-thema mee. Interne naam- en detail-links wisselen alleen dit inhoudsvak en tonen een onderstreepte teruglink met een pijl naar de ranglijst. De knoppen blijven altijd WordPress-native. Met `modus="iframe"` wordt de oorspronkelijke HTML via een beveiligde plugin-proxy getoond; dat is nuttig wanneer de originele Rokade-layout belangrijker is.

Wanneer de export ze bevat, verschijnen bij iedere groep ook de tabbladen **Kruistabel** en **Scoretabel**. Samen met **Ranglijst** vormen ze een tabbladbalk die visueel aansluit op de tabel eronder; de gekozen weergave laadt in-place.

De waarde van `seizoen` moet exact de naam van een geïndexeerde submap zijn, bijvoorbeeld `2026-2027`, `voorjaar-2026` of `archief`. Een afwijkende waarde toont geen standen en kan nooit naar bestanden buiten de ingestelde standenmap verwijzen.

De waarde van `bron` is het label van een bronpad in kleine letters met koppeltekens, dus `Senioren` wordt `bron="senioren"`. Zonder `bron` wordt de eerste ingestelde bron gebruikt. De instellingenpagina toont per bron de herkende naam en het aantal gevonden seizoenen.

## Lokale thema's

`themes/` wordt read-only gemount naar `wp-content/themes/`. De lokale testinstallatie gebruikt `hoogland-2023-dev` als child theme; de vereiste parent `hoogland` staat in dezelfde map. De thema's worden meegecommit, zodat een clone zonder extra lokale kopie direct kan draaien.

Stop de lokale omgeving desgewenst weer met `docker compose down`. De database en WordPress-installatie blijven dan in de Docker-volumes bewaard.

## Productiepakket maken

Maak een ZIP-bestand dat rechtstreeks via **Plugins → Nieuwe plugin toevoegen → Plugin uploaden** in WordPress kan worden geïnstalleerd met:

```sh
npm run package
```

Het pakket verschijnt als `dist/rokade-standings-<versie>.zip`. Het bevat alleen de plugincode, assets en README; lokale exportbestanden, thema's, Docker-bestanden en ontwikkelbestanden blijven buiten het archief.

## Releases en automatische updates

Elke push naar `main` draait de workflow [`.github/workflows/release.yml`](.github/workflows/release.yml). Die controleert eerst de PHP-syntaxis van alle bestanden, bouwt daarna hetzelfde pakket als `npm run package` en bewaart het als build-artifact — ook wanneer de versie niet veranderd is, zodat er altijd een installeerbare zip van de laatste `main` klaarstaat.

Een pull request naar `main` doorloopt dezelfde lint, bouw en controle, alleen zonder te publiceren. Een fout in het pakket of de feed valt daarmee op voordat hij op `main` staat.

Staat er in `rokade-standings.php` een versie waarvoor nog geen tag `v<versie>` bestaat, dan publiceert de workflow die versie bovendien als GitHub-release met twee bestanden: de zip en `update.json`. Een release uitbrengen is dus niets meer dan het versienummer in de header én in `ROKADE_STANDINGS_VERSION` ophogen en dat naar `main` pushen. Een verlaagd versienummer wordt geweigerd, omdat GitHub de nieuwste release als "latest" aanwijst en de feed daarmee zou terugvallen.

`update.json` is de update-feed. De plugin draagt de header `Update URI: https://github.com/jvanoostveen/rokade-standings`, waardoor WordPress voor updates niet bij wordpress.org maar bij [`Rokade_Standings_Updater`](includes/class-rokade-standings-updater.php) aanklopt. Die leest de feed op de vaste URL die GitHub altijd naar de nieuwste release laat wijzen:

```
https://github.com/jvanoostveen/rokade-standings/releases/latest/download/update.json
```

Een site ziet de nieuwe versie daarna gewoon bij **Dashboard → Updates** en in de pluginlijst, inclusief "Details bekijken" met de changelog, en kan automatische updates aanzetten. Het antwoord wordt twaalf uur bewaard; **Opnieuw controleren** wist die cache.

Zolang de repository privé is, geeft die URL een 404. Dat is geen probleem: een mislukte controle (404, netwerkstoring, ongeldige JSON, of een pakket-URL die niet op GitHub staat) levert nooit een foutmelding of vertraagde beheerpagina op — WordPress hoort dan simpelweg niets over een nieuwe versie, en de mislukking wordt een uur onthouden zodat niet elke paginaweergave opnieuw op hetzelfde verzoek wacht. Zodra de repository openbaar is, werkt de feed zonder verdere wijziging.

De feed is ook lokaal te bouwen, bijvoorbeeld om te controleren wat er gepubliceerd wordt:

```sh
npm run manifest
```

## Upgraden vanaf 0.1.x

Vanaf 0.2.0 heet alles in de plugin intern `rokade-standings`: de klassen, het tekstdomein, de CSS-klassen (`rokade-standings__…`), de optie `rokade_standings_settings`, het blok `rokade-standings/standings` en de queryparameters van het bestandsendpoint. Een bestaande installatie hoeft daar niets voor te doen:

- De instellingen worden bij het eerste bezoek aan het beheer automatisch overgezet; tot die tijd leest de voorkant de oude optie.
- Pagina's die het blok nog onder de oude naam `schaken-standen/rokade` bevatten, blijven werken en bewerkbaar. Die naam is als verborgen alias geregistreerd; nieuwe blokken krijgen de nieuwe naam.
- Eigen CSS in het thema dat op `.schaken-standen…`-klassen mikte, moet naar `.rokade-standings…` worden omgezet.
- Deep links met `rokade_bron`, `rokade_seizoen`, `rokade_categorie` en `rokade_competitie` zijn ongewijzigd. De shortcode blijft `[rokade]`.

## Productiebron

Kopieer iedere exportmap naar een leesbare locatie buiten de plugin, bijvoorbeeld:

```
wp-content/uploads/standen/2026-2027/kroon/C1Index.htm
wp-content/uploads/senioren/2026-2027/kroon/C1Index.htm
```

Vul dan per regel de absolute locatie plus een label in:

```
/var/www/html/wp-content/uploads/standen | Jeugd
/var/www/html/wp-content/uploads/senioren | Senioren
```

De plugin accepteert alleen seizoensmappen die door de index zijn gevonden en valideert ieder verzoek tegen het bronpad van de gekozen bron, zodat een URL nooit andere serverbestanden kan uitlezen. Verdwijnt een bron uit de instellingen, dan stoppen de bijbehorende bestanden direct met laden; daar wordt niet op de cache gewacht.
