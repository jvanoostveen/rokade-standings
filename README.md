# Rokade Standen

WordPress-plugin die de HTML-standen van Rokade op een pagina toont. De plugin scant de `C*Index.htm`-bestanden van een export, zowel per competitie (`<seizoen>/<groep>/C1Index.htm`) als rechtstreeks in de seizoensmap (`<seizoen>/C1Index.htm`). De `<title>` van elk bestand wordt de knoptekst; titels met “Doorgeef” en “Snelschaak” vormen automatisch die categorieën, de rest valt onder **Interne competitie**.

Een uitgebreide handleiding met schermafbeeldingen en video staat in [`docs/handleiding/`](docs/handleiding/README.md). Voor ontwikkelaars: zie [`docs/ontwikkeling.md`](docs/ontwikkeling.md).

## Installeren en bijwerken

Installeer de zip van de nieuwste release via **Plugins → Nieuwe plugin toevoegen → Plugin uploaden**. Nieuwe versies verschijnen daarna vanzelf bij **Dashboard → Updates** en in de pluginlijst, inclusief de changelog onder "Details bekijken"; automatische updates zijn daar ook aan te zetten.

## Bronnen instellen

Kopieer iedere exportmap naar een leesbare locatie buiten de plugin, bijvoorbeeld:

```
wp-content/uploads/standen/2026-2027/kroon/C1Index.htm
wp-content/uploads/senioren/2026-2027/kroon/C1Index.htm
```

Vul op **Instellingen → Rokade Standen** per regel een pad en een label in:

```
/wp-content/uploads/standen | Jeugd
/wp-content/uploads/senioren | Senioren
```

Paden tellen vanaf de **hoofdmap van de bronnen** (onder **Geavanceerd**). Leeg is dat de WordPress-map, zodat het volledige serverpad niet bekend hoeft te zijn. Staan de exports buiten de site, vul dan die map in als hoofdmap, of `/` om volledige serverpaden te gebruiken. Een volledig pad uit een eerdere versie blijft werken. De instellingenpagina toont per bron de herkende naam en het aantal gevonden seizoenen.

De index van alle bronnen wordt bewaard (standaard 15 minuten, instelbaar), op de achtergrond opgewarmd voordat hij verloopt en is op de instellingenpagina direct te vernieuwen.

De plugin leest alleen seizoensmappen die de index heeft gevonden, binnen het bronpad van de gekozen bron; een URL kan dus nooit andere serverbestanden uitlezen. Verdwijnt een bron uit de instellingen, dan stoppen de bijbehorende bestanden direct met laden.

## Standen op een pagina

In de blok-editor is er het blok **Rokade standen**. Kies in de zijbalk de bron, het seizoen, de competitie en de weergave. De bronkeuze verschijnt zodra er meer dan één bron is; seizoen en competitie worden leeggemaakt zodra ze in de gekozen bron niet bestaan.

Buiten de blok-editor werkt de shortcode:

```
[rokade seizoen="2025-2026"]
[rokade bron="senioren" seizoen="2025-2026"]
[rokade seizoen="2026-2027" categorie="interne-competitie"]
[rokade seizoen="2025-2026" categorie="snelschaken"]
[rokade seizoen="2025-2026" modus="iframe"]
```

- `seizoen` is exact de naam van een geïndexeerde seizoensmap, bijvoorbeeld `2026-2027` of `archief`. Een onbekende waarde toont geen standen.
- `bron` is het label in kleine letters met koppeltekens: `Senioren` wordt `senioren`. Zonder `bron` geldt de eerste bron.
- `modus` is standaard `inline`: de ranglijst krijgt de lettertypes, kleuren en links van het thema, en naam- en detaillinks openen in hetzelfde vak met een teruglink naar de ranglijst. `iframe` toont de oorspronkelijke Rokade-layout.

Bevat de export ze, dan verschijnen bij iedere groep naast **Ranglijst** ook de tabbladen **Kruistabel** en **Scoretabel**.
