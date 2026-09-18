# Rokade Standen

WordPress-plugin voor de HTML-standen die Rokade naar een map op de server schrijft. De plugin scant uitsluitend de kleine `C*Index.htm`-bestanden. Hun `<title>` wordt de knoptekst; titels met “Doorgeef” en “Snelschaak” vormen automatisch die categorieën, de rest valt onder **Interne competitie**.

Er zijn meerdere bronpaden mogelijk, elk met een eigen label — bijvoorbeeld een aparte export voor de jeugd en voor de senioren. Elke bron wordt los geïndexeerd en is per blok of shortcode te kiezen.

De cache voorkomt dat bij elke paginaview de directory wordt doorzocht. De index is een WordPress transient met een instelbare levensduur (standaard 15 minuten), wordt elk uur opgewarmd via WP-Cron en kan via de instellingen onmiddellijk worden vernieuwd.

## Lokaal starten met OrbStack/Docker

```sh
docker compose up -d
```

Plaats eerst lokaal een Rokade-export onder `docs/current/standen/` (alles onder `docs/current/` staat bewust in `.gitignore`). Open daarna <http://localhost:8080>, voltooi de WordPress-installatie en activeer **Rokade Standen**. Ga naar **Instellingen → Rokade Standen** en vul dit bronpad in:

```
/standen | Jeugd
```

De lokale exportmappen worden in de container read-only gemount: `docs/current/standen` als `/standen` en `docs/current/senioren` als `/senioren`. Een export als `docs/current/standen/2026-2027/…` is in de container dus beschikbaar als `/standen/2026-2027/…`. Met twee bronnen wordt het veld:

```
/standen | Jeugd
/senioren | Senioren
```

Een extra bron toevoegen betekent een mountregel in `docker-compose.yml` en een regel in dit veld.

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

Standaard is de modus `inline`: het ranglijstdeel wordt door de plugin veilig ingelezen en krijgt de lettertypes, kleuren en links van het WordPress-thema mee. Interne naam- en detail-links wisselen alleen dit inhoudsvak en tonen een knop om terug te keren naar de ranglijst. De knoppen blijven altijd WordPress-native. Met `modus="iframe"` wordt de oorspronkelijke HTML via een beveiligde plugin-proxy getoond; dat is nuttig wanneer de originele Rokade-layout belangrijker is.

Wanneer de export ze bevat, verschijnen bij iedere groep ook de knoppen **Kruistabel** en **Scoretabel**. Deze laden eveneens in-place.

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
