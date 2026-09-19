# Handleidingscenario

> Werk je met Claude Code? De skill `handleiding` in `.claude/skills/` vat samen wat er bij
> welke wijziging opnieuw opgenomen moet worden en welke valkuilen er al een keer hebben
> toegeslagen.

Dit gereedschap speelt de handleiding na in de lokale WordPress van `docker-compose.yml`
en legt hem vast: schermafbeeldingen als naslag en een video per deel. Alles wat de
handleiding toont komt uit een echte installatie, dus na een nieuwe feature neem je het
scenario opnieuw op in plaats van beelden bij te knippen.

De uitvoer komt in [`docs/handleiding/media/`](../../docs/handleiding/media); de tekst
van de handleiding zelf staat in [`docs/handleiding/README.md`](../../docs/handleiding/README.md).

## Eenmalig klaarzetten

```sh
docker compose up -d
cd tools/handleiding
npm install
npx playwright install chromium
cp .env.example .env   # vul WP_ADMIN_PASSWORD in
```

De lokale export hoort onder `docs/current/standen/` en `docs/current/senioren/` te staan,
precies zoals de hoofd-README beschrijft; het scenario verwijst naar `/standen` en
`/senioren` in de container.

## Opnemen

```sh
npm run capture              # beide delen
npm run capture:instellen    # alleen deel 1
npm run capture:gebruiken    # alleen deel 2
npm run seed                 # alleen de uitgangsstaat klaarzetten, niets opnemen
npm run pdf                  # de PDF-versie opnieuw bouwen uit de handleiding
npm run handleiding          # opnemen én de PDF bijwerken
npm run controleer           # tekst, beelden, video en PDF tegen elkaar houden
```

`npm run controleer` is het vangnet na een wijziging. Het meldt beelden waar de handleiding
naar verwijst maar die niet bestaan, beelden die nergens in de tekst staan, een opname van een
oudere pluginversie, een leeg beeld, een ontbrekende video, een blokoptie die de handleiding
niet noemt, en een PDF die ouder is dan de beelden.

Handige omgevingsvariabelen (of regels in `.env`):

| Variabele | Betekenis |
| --- | --- |
| `WP_BASE_URL` | Adres van de lokale site, standaard `http://localhost:8080`. |
| `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` | Beheerder waarmee het scenario inlogt. |
| `WP_LOCALE` | Taal van de beheeromgeving op de beelden, standaard `nl_NL`. |
| `CAPTURE_PACE` | Tempo: `1` is normaal, `1.5` rustiger, `0.6` sneller. |
| `CAPTURE_SCALE` | Pixelverhouding van de schermafbeeldingen, standaard `2`. |

## Wat het scenario met je lokale installatie doet

Het scenario zet de uitgangsstaat zelf klaar via WP-CLI (de `wpcli`-service in
`docker-compose.yml`), zodat een herhaling hetzelfde beeld geeft:

- de plugin wordt geactiveerd en de beheertaal op `WP_LOCALE` gezet;
- `schaken_standen_settings` wordt overschreven — deel 1 begint zonder bronpad, deel 2
  met de bronnen `/standen | Jeugd` en `/senioren | Senioren`;
- de standenindex (transient) wordt gewist;
- een eerdere voorbeeldpagina **Standen jeugd** en losse auto-concepten worden verwijderd.

Andere pagina's, je gebruikers en je database blijven ongemoeid, maar draai het dus op de
lokale Docker-omgeving en niet tegen productie.

## Opbouw

| Bestand | Rol |
| --- | --- |
| `capture.mjs` | Regie: inloggen, scènes draaien, video opbergen, `opname.json` schrijven. |
| `lib/config.mjs` | Instellingen en paden; leest `.env`. |
| `lib/wp.mjs` | WP-CLI in de `wpcli`-container. |
| `lib/seed.mjs` | De uitgangsstaat per deel, plus de waarden die de handleiding noemt. |
| `lib/ui.mjs` | Bijschrift, cursor, tempo en schermafbeeldingen zonder die hulpmiddelen. |
| `lib/editor.mjs` | Handgrepen voor de blok-editor (invoegmenu, zijbalk, publiceren). |
| `scenes/instellen.mjs` | Deel 1: installeren en instellen. |
| `scenes/gebruiken.mjs` | Deel 2: blok plaatsen, elke optie langslopen, publiceren, resultaat. |
| `pdf.mjs` | Bouwt `docs/handleiding/Handleiding-Rokade-Standen.pdf` uit dezelfde tekst. |
| `controleer.mjs` | Houdt tekst, beelden, video en PDF tegen elkaar. |

`docs/handleiding/media/opname.json` houdt bij wanneer er is opgenomen, met welke versies,
welke stappen er in de video zitten (met de seconde erbij) en welke opties de dropdowns op
dat moment aanboden. Verschilt die optielijst na een nieuwe feature, dan weet je meteen
welk stuk tekst in de handleiding bijgewerkt moet worden.

## Een stap toevoegen

Scènes zijn gewone scripts. Een nieuwe stap is een regel in `scenes/*.mjs`:

```js
await ui.stap('Wat de kijker nu ziet gebeuren.');
await ui.klik(page.getByRole('button', { name: 'Publiceren' }));
await ui.schermafbeelding('gebruiken-13-iets', { locator: paneel });
```

`ui.stap()` zet het bijschrift in de video, `ui.klik()`/`ui.typ()`/`ui.kies()` bewegen de
zichtbare cursor mee, en `ui.schermafbeelding()` verbergt die hulpmiddelen weer voordat het
beeld wordt vastgelegd. Een nieuwe dropdown loop je met `loopOptiesLangs()` langs; die leest
de opties uit de editor zelf, dus nieuwe keuzes komen vanzelf in de video en in `opname.json`.

## PDF zonder video

`npm run pdf` maakt van `docs/handleiding/README.md` een PDF met omslag, inhoudsopgave en
paginanummers. De tekst is dezelfde; alleen de webdelen vallen eruit. Wat de PDF niet krijgt,
staat in de bron tussen markeringen:

```html
<!-- alleen-web:start -->
<video src="media/video/instellen.mp4" controls width="880"></video>
<!-- alleen-web:eind -->
```

Zo blijft er één bron voor beide vormen: de PDF verwijst nooit naar beelden die je op papier
niet kunt afspelen, en de webversie hoeft niets te missen. Neem je opnieuw op, draai dan ook
`npm run pdf` — de omslag noemt de plugin- en WordPress-versie uit `opname.json`.

## Video

Playwright neemt op in `webm`; `capture.mjs` maakt daar een `mp4` van, in deze volgorde:

1. `ffmpeg` op je pad, als die er is;
2. anders `ffmpeg` in een container — standaard `jrottenberg/ffmpeg:7.1-alpine`, te wijzigen met
   `FFMPEG_IMAGE`. Er hoeft dus niets op de host geïnstalleerd te worden;
3. lukt geen van beide, dan blijft het een `webm`, gecomprimeerd met de `ffmpeg` die Playwright
   zelf meebrengt.

Met `--keep-webm` blijft de oorspronkelijke opname naast de `mp4` staan.
