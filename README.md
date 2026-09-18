# Rokade Standen

WordPress-plugin voor de HTML-standen die Rokade naar een map op de server schrijft. De plugin scant uitsluitend de kleine `C*Index.htm`-bestanden. Hun `<title>` wordt de knoptekst; titels met “Doorgeef” en “Snelschaak” vormen automatisch die categorieën, de rest valt onder **Interne competitie**.

De cache voorkomt dat bij elke paginaview de directory wordt doorzocht. De index is een WordPress transient met een instelbare levensduur (standaard 15 minuten), wordt elk uur opgewarmd via WP-Cron en kan via de instellingen onmiddellijk worden vernieuwd.

## Lokaal starten met OrbStack/Docker

```sh
docker compose up -d
```

Plaats eerst lokaal een Rokade-export onder `docs/current/standen/` (deze map staat bewust in `.gitignore`). Open daarna <http://localhost:8080>, voltooi de WordPress-installatie en activeer **Rokade Standen**. Ga naar **Instellingen → Rokade Standen** en vul dit bronpad in:

```
/standen
```

De lokale exportmap wordt in de container read-only als `/standen` gemount. Een export als `docs/current/standen/2026-2027/…` is in de container dus beschikbaar als `/standen/2026-2027/…`. Maak vervolgens bijvoorbeeld een pagina met:

```
[rokade seizoen="2025-2026"]
```

Handige varianten:

```
[rokade seizoen="2026-2027" categorie="interne-competitie"]
[rokade seizoen="2025-2026" categorie="snelschaken"]
[rokade seizoen="2025-2026" modus="iframe"]
```

Standaard is de modus `inline`: het ranglijstdeel wordt door de plugin veilig ingelezen en krijgt de lettertypes, kleuren en links van het WordPress-thema mee. Interne naam- en detail-links wisselen alleen dit inhoudsvak en tonen een knop om terug te keren naar de ranglijst. De knoppen blijven altijd WordPress-native. Met `modus="iframe"` wordt de oorspronkelijke HTML via een beveiligde plugin-proxy getoond; dat is nuttig wanneer de originele Rokade-layout belangrijker is.

Wanneer de export ze bevat, verschijnen bij iedere groep ook de knoppen **Kruistabel** en **Scoretabel**. Deze laden eveneens in-place.

De waarde van `seizoen` moet exact de naam van een geïndexeerde submap zijn, bijvoorbeeld `2026-2027`, `voorjaar-2026` of `archief`. Een afwijkende waarde toont geen standen en kan nooit naar bestanden buiten de ingestelde standenmap verwijzen.

## Lokale thema's

`themes/` wordt read-only gemount naar `wp-content/themes/`. De lokale testinstallatie gebruikt `hoogland-2023-dev` als child theme; de vereiste parent `hoogland` staat in dezelfde map. De thema's worden meegecommit, zodat een clone zonder extra lokale kopie direct kan draaien.

Stop de lokale omgeving desgewenst weer met `docker compose down`. De database en WordPress-installatie blijven dan in de Docker-volumes bewaard.

## Productiebron

Kopieer de exportmap naar een leesbare locatie buiten de plugin, bijvoorbeeld:

```
wp-content/uploads/standen/2026-2027/kroon/C1Index.htm
```

Vul dan de absolute locatie van de map `standen` in. De plugin accepteert alleen seizoensmappen die door de index zijn gevonden en valideert ieder verzoek tegen dat bronpad, zodat een URL nooit andere serverbestanden kan uitlezen.
