---
name: handleiding
description: Werk de handleiding van Rokade Standen bij - de tekst, de schermafbeeldingen, de twee video's en de PDF. Gebruik dit zodra er iets verandert aan de instellingen, de blokopties, de knoppen, de weergave of de teksten die de gebruiker ziet, en ook wanneer alleen de beelden verouderd zijn.
---

# De handleiding bijwerken

De handleiding is geen losse map met beelden: het scenario in `tools/handleiding/` speelt de
hele route na in de lokale Docker-WordPress en legt hem vast. Bij een wijziging neem je dus
opnieuw op in plaats van beelden bij te knippen.

## Wat waar staat

| Bestand | Rol |
| --- | --- |
| `docs/handleiding/README.md` | De tekst. Eén bron voor web én PDF. |
| `docs/handleiding/media/schermafbeeldingen/` | 31 beelden, opgenomen op 2× pixelverhouding. |
| `docs/handleiding/media/video/` | `instellen.mp4` (≈1 min) en `gebruiken.mp4` (≈2 min). |
| `docs/handleiding/media/opname.json` | Wat er is opgenomen: versies, stappen met seconde, aangeboden keuzes, waarschuwingen. |
| `docs/handleiding/Handleiding-Rokade-Standen.pdf` | Dezelfde tekst zonder de webdelen. |
| `tools/handleiding/` | Het scenario. Zie de README daar voor de opbouw per bestand. |

## Wat te doen bij welke wijziging

| Wijziging in de plugin | Aanpakken |
| --- | --- |
| Veld op **Instellingen → Rokade Standen** erbij, weg of anders | Stap toevoegen in `scenes/instellen.mjs`, tekst in deel 1 bijwerken, `npm run capture:instellen`. |
| Keuze in de blokzijbalk erbij of anders | `scenes/gebruiken.mjs` loopt de keuzes zelf langs; alleen de tabel in deel 2 bijwerken en `npm run capture:gebruiken`. Een nieuwe optie die nergens in de tekst staat, meldt `npm run controleer`. |
| Andere knoppen of opmaak op de voorkant | Alleen `npm run capture:gebruiken`; de `resultaat-*`-beelden komen uit datzelfde deel. |
| Nieuwe categorie of weergavemodus | Tabel “Competitie” of “Weergave” in deel 2 aanvullen en opnieuw opnemen. |
| Alleen een versienummer of tekstje | `npm run capture` (beide delen) zodat `opname.json` weer bij de code past. |
| Nieuwe export of ander seizoen in `docs/current/` | `npm run capture`; seizoenen zijn gegevens, de tekst hoeft niet mee. |

## Opnemen

```sh
docker compose up -d
cd tools/handleiding
npm install                    # eenmalig, plus npx playwright install chromium
cp .env.example .env           # eenmalig: WP_ADMIN_PASSWORD invullen
npm run handleiding            # opnemen en de PDF bouwen
npm run controleer             # tekst, beelden, video en PDF tegen elkaar houden
```

Losse delen: `npm run capture:instellen`, `npm run capture:gebruiken`, `npm run pdf`.
Het scenario zet zijn eigen uitgangsstaat klaar via WP-CLI (plugin actief, Nederlandse
beheertaal, bronpaden, index leeg, oude voorbeeldpagina weg), dus draai het op de lokale
Docker-omgeving en niet tegen productie.

## Altijd nalopen na een opname

1. `npm run controleer` moet groen zijn. Die vergelijkt de pluginversie met `opname.json`,
   controleert of elk beeld in de tekst staat en omgekeerd, of geen beeld leeg is, of de
   video's bestaan en of de PDF niet ouder is dan de beelden.
2. Kijk de video's kort na op het moment dat je hebt gewijzigd; `opname.json` noemt per stap
   de seconde, dus je hoeft niet te zoeken.
3. Bouw de PDF opnieuw zodra een beeld of de tekst is veranderd: `npm run pdf`.

## Een stap toevoegen aan een scène

```js
await ui.stap('Wat de kijker nu ziet gebeuren.');
await ui.klik(page.getByRole('button', { name: 'Publiceren' }));
await ui.schermafbeelding('gebruiken-13-iets', { locator: paneel, maxHoogte: 330 });
```

- `ui.stap()` zet het bijschrift in de video, `ui.klik()` / `ui.typ()` / `ui.kies()` bewegen de
  zichtbare cursor mee.
- Elke paginawissel loopt via `ui.ga(url)` of `ui.overgang(() => knop.click())`, nooit via een
  kale `page.goto()`: het doek dekt de witte flits van de browser af.
- `ui.schermafbeelding()` verbergt bijschrift en cursor en wacht tot die fade klaar is.
  `locator` mag een lijst zijn (het omhullende kader), `maxHoogte` kapt een paneel af dat
  anders vol lege ruimte staat.

## Valkuilen die al een keer hebben toegeslagen

- **Overlay alleen in het hoofdvenster.** Het script draait in elk frame, ook in de
  bewerkcanvas van Gutenberg. Zonder de `window.top === window.self`-grens legt het doek daar
  een wit vlak dat niemand meer weghaalt: lege canvas in video én schermafbeeldingen.
- **Wachten op de voorvertoning hoort in het canvas-frame.** In het hoofddocument bestaat
  `.schaken-standen-block-preview` niet, waardoor elke wachtactie 30 seconden uitliep en de
  video negen minuten werd.
- **Een verticale streep in een tabelcel** (`zoekterm | knopnaam`) breekt de kolom; schrijf
  `\|`.
- **Beeldgrootte in de PDF** komt uit `pdf.mjs`: hele vensters krijgen 116 mm omdat ze context
  zijn, uitsnedes ruwweg hun schermgrootte zodat de tekst leesbaar blijft. Pas dat aan in
  `afdrukmaat()`, niet met een `<img width>` in de tekst.
- **Wat niet in de PDF hoort** staat in `docs/handleiding/README.md` tussen
  `<!-- alleen-web:start -->` en `<!-- alleen-web:eind -->`. Video's en repo-uitleg horen daar
  tussen, de rest niet.
