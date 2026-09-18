# Standen op disk

WordPress-plugin voor de HTML-standen die Rokade naar een map op de server schrijft. De plugin scant uitsluitend de kleine `C*Index.htm`-bestanden. Hun `<title>` wordt de knoptekst; titels met “Doorgeef” en “Snelschaak” vormen automatisch die categorieën, de rest valt onder **Interne competitie**.

De cache voorkomt dat bij elke paginaview de directory wordt doorzocht. De index is een WordPress transient met een instelbare levensduur (standaard 15 minuten), wordt elk uur opgewarmd via WP-Cron en kan via de instellingen onmiddellijk worden vernieuwd.

## Lokaal starten met OrbStack/Docker

```sh
docker compose up -d
```

Plaats eerst lokaal een Rokade-export onder `docs/current/standen/` (deze map staat bewust in `.gitignore`). Open daarna <http://localhost:8080>, voltooi de WordPress-installatie en activeer **Schaken in Hoogland – Standen op disk**. Ga naar **Instellingen → Schaken standen** en vul dit bronpad in:

```
/var/www/html/wp-content/uploads/standen
```

De lokale exportmap wordt in de container read-only op die locatie gemount. Maak vervolgens bijvoorbeeld een pagina met:

```
[schaken_standen seizoen="2025-2026"]
```

Handige varianten:

```
[schaken_standen seizoen="2026-2027" categorie="interne-competitie"]
[schaken_standen seizoen="2025-2026" categorie="snelschaken"]
[schaken_standen seizoen="2025-2026" modus="iframe"]
```

Standaard is de modus `inline`: het ranglijstdeel wordt door de plugin veilig ingelezen en krijgt de lettertypes, kleuren en links van het WordPress-thema mee. De knoppen blijven altijd WordPress-native. Met `modus="iframe"` wordt de oorspronkelijke HTML via een beveiligde plugin-proxy getoond; dat is nuttig wanneer de originele Rokade-layout belangrijker is.

Stop de lokale omgeving desgewenst weer met `docker compose down`. De database en WordPress-installatie blijven dan in de Docker-volumes bewaard.

## Productiebron

Kopieer de exportmap naar een leesbare locatie buiten de plugin, bijvoorbeeld:

```
wp-content/uploads/standen/2026-2027/kroon/C1Index.htm
```

Vul dan de absolute locatie van de map `standen` in. De plugin accepteert alleen seizoensmappen met naam `JJJJ-JJJJ` en valideert ieder verzoek tegen dat bronpad, zodat een URL nooit andere serverbestanden kan uitlezen.
