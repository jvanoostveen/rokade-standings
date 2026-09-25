# Handleiding Rokade Standen

Deze handleiding laat zien hoe je de plugin **Rokade Standen** installeert, instelt en
gebruikt om de standen uit een Rokade-export op een WordPress-pagina te zetten.

De handleiding bestaat uit twee delen:

1. **[Instellen](#deel-1--installeren-en-instellen)** — de plugin op de site zetten en de
   bronpaden aanwijzen. Dit doe je één keer.
2. **[Gebruiken](#deel-2--standen-op-een-pagina-zetten)** — een pagina maken en het blok
   **Rokade standen** plaatsen. Dit doe je voor elke pagina met standen.

Onderaan staat het [voorbeeldresultaat](#het-resultaat) en een lijst met
[veelvoorkomende vragen](#als-er-iets-niet-klopt).

<!-- alleen-web:start -->
> De beelden in deze handleiding zijn opgenomen in een echte installatie. Verandert de
> plugin, dan neem je het scenario opnieuw op; zie [Beelden opnieuw maken](#beelden-opnieuw-maken).
> Deze handleiding is er ook als [PDF zonder video](Handleiding-Rokade-Standen.pdf).
<!-- alleen-web:eind -->

---

## Deel 1 — Installeren en instellen

<!-- alleen-web:start -->
<video src="media/video/instellen.mp4" controls width="880"></video>

▶︎ **Video:** [media/video/instellen.mp4](media/video/instellen.mp4) — het hele eerste deel
achter elkaar.
<!-- alleen-web:eind -->

### 1. De plugin uploaden

Maak eerst een pluginpakket met `npm run package`; dat levert
`dist/rokade-standings-<versie>.zip`. Ga in WordPress naar **Plugins → Nieuwe plugin
toevoegen → Plugin uploaden**, kies het ZIP-bestand en klik op **Nu installeren**.

![Plugin uploaden](media/schermafbeeldingen/instellen-01-plugin-uploaden.png)

Bij een update van een al geïnstalleerde plugin vraagt WordPress of je de bestaande versie
wilt vervangen; dat mag, je instellingen blijven staan.

### 2. De plugin activeren

Na het installeren staat **Rokade Standen** in de lijst onder **Plugins**. Klik op
**Activeren**.

![Rokade Standen in de pluginlijst](media/schermafbeeldingen/instellen-02-plugin-actief.png)

### 3. De instellingen openen

De instellingen staan onder **Instellingen → Rokade Standen**.

![Het menu Instellingen met Rokade Standen](media/schermafbeeldingen/instellen-03-menu.png)

Een verse installatie kent nog geen bronpad en toont dus nog geen standen.

![De instellingenpagina zonder bronpad](media/schermafbeeldingen/instellen-04-leeg.png)

### 4. Bronpaden op de server

Een **bronpad** is de map waarin de seizoensmappen staan — dus de map waar
`2026-2027/kroon/C1Index.htm` onder valt, niet de seizoensmap zelf. Zet per regel één bron,
in de vorm `pad | label`:

```
/wp-content/uploads/standen | Jeugd
/wp-content/uploads/senioren | Senioren
```

![Het veld Bronpaden op de server](media/schermafbeeldingen/instellen-05-bronpaden.png)

- Het **pad** telt vanaf de WordPress-map: `/wp-content/uploads/standen`, of `/standen`
  voor een map direct in de webroot. Het volledige serverpad hoef je dus niet te weten.
  Staan de exports buiten de site, zie dan [Hoofdmap van de bronnen](#8-hoofdmap-van-de-bronnen-geavanceerd).
  Kopieer de Rokade-export naar een map
  buiten de plugin, bijvoorbeeld onder `wp-content/uploads/`; bij een plugin-update blijft
  hij dan staan.
- Het **label** is de naam die je in het blok terugziet, en bepaalt ook de naam in de
  shortcode: `Jeugd` wordt `bron="jeugd"`.
- Laat je het label weg, dan gebruikt de plugin de mapnaam.
- De volgorde telt: de bovenste regel is de bron die wordt gebruikt als een blok of
  shortcode er zelf geen kiest.

> **Let op:** elk `.htm`- of `.html`-bestand onder een bronpad is via de site openbaar
> leesbaar, zonder inloggen. Wijs daarom precies een standenmap aan en nooit een map
> daarboven.

### 5. Cacheduur

De plugin doorzoekt de mappen niet bij elke paginaweergave: de gevonden competities staan
in een cache. De cacheduur (in minuten, standaard 15) bepaalt hoe lang die blijft staan én
hoe vaak WP-Cron hem op de achtergrond ververst.

![Het veld Cacheduur](media/schermafbeeldingen/instellen-06-cacheduur.png)

Een korte duur betekent dat een nieuwe export sneller zichtbaar is; een lange duur scheelt
werk op de server. Tussen 5 en 60 minuten werkt in de praktijk prima. Je hoeft er niet op te
wachten: met **Nu opnieuw indexeren** (stap 9) is een nieuwe export meteen zichtbaar.

### 6. Groepen en volgorde

De knoppen binnen de interne competitie komen uit de titels van de exportbestanden. Hier
bepaal je welke groepen er zijn, hoe ze heten en in welke volgorde ze staan — één groep per
regel:

```
Starters
Pupillen
Junioren
Verkenners
Meester-/Kroon
Meester
Kroon
```

![Het veld Groepen en volgorde](media/schermafbeeldingen/instellen-07-groepen.png)

- De regel is tegelijk de knopnaam. Wil je een andere knoptekst dan de zoekterm, gebruik dan
  `zoekterm | knopnaam`.
- De plugin negeert “groep” in de titel: `Starters` herkent dus ook “Startersgroep Voorjaar
  2026”.
- Groepen die je hier niet noemt verdwijnen niet; ze komen achter deze lijst te staan.
- De periode (bijvoorbeeld **Voorjaar 2026**) haalt de plugin zelf uit de titel en zet hij
  als kopje boven de knoppen. Staat er geen periode in de titel, dan wordt dat
  “Seizoen 2026-2027”.

### 7. Knopnamen voor blokcompetities

Doorgeefschaak en snelschaken worden in blokken gespeeld. Per categorie stel je in hoe die
knoppen heten, met `{nummer}` voor het bloknummer uit de titel:

```
doorgeefschaak | Blok {nummer}
snelschaken | Blok {nummer}
```

![Het veld Blokknoppen](media/schermafbeeldingen/instellen-08-blokknoppen.png)

### 8. Hoofdmap van de bronnen (geavanceerd)

Onder **Geavanceerd** staat de map waar alle bronpaden vanaf tellen. Meestal laat je dit
veld leeg: dan is dat de WordPress-map, en onder het veld Bronpaden zie je welke map dat
op jouw server is.

Vul het alleen in als de exports buiten de site staan. Met bijvoorbeeld `/srv/rokade` als
hoofdmap wordt `/standen` de map `/srv/rokade/standen`. Met `/` als hoofdmap geef je bij
de bronpaden het volledige serverpad op.

Klik daarna op **Wijzigingen opslaan**.

### 9. Controleren en verversen

Na het opslaan ververst de plugin de index en toont hij per bron wat er is gevonden.

![De opgeslagen instellingen](media/schermafbeeldingen/instellen-09-opgeslagen.png)

Onder **Index verversen** staat per bron het label, de naam voor de shortcode en het aantal
gevonden seizoenen. Staat er `0 seizoenen gevonden`, dan klopt het pad niet of kan de
webserver de map niet lezen.

![Gevonden bronnen en seizoenen](media/schermafbeeldingen/instellen-10-bronnen-gevonden.png)

Zet je een nieuwe export op de server, dan haalt **Nu opnieuw indexeren** die meteen binnen,
zonder te wachten tot de cache verloopt.

![Melding dat de index is vernieuwd](media/schermafbeeldingen/instellen-11-index-vernieuwd.png)

### 10. Shortcode (alternatief voor het blok)

Onderaan de instellingenpagina staat de shortcode. Die is handig in een thema of widget waar
geen blok-editor is.

![De shortcode op de instellingenpagina](media/schermafbeeldingen/instellen-12-shortcode.png)

```
[rokade bron="jeugd" seizoen="2026-2027"]
[rokade bron="senioren" categorie="snelschaken"]
[rokade seizoen="2025-2026" modus="iframe"]
```

| Kenmerk | Betekenis |
| --- | --- |
| `bron` | Het label van een bronpad in kleine letters met koppeltekens. Zonder `bron` wordt de bovenste bron gebruikt. |
| `seizoen` | De naam van een seizoensmap, bijvoorbeeld `2026-2027` of `voorjaar-2026`. Zonder `seizoen` het meest recente. |
| `categorie` | `interne-competitie`, `doorgeefschaak` of `snelschaken`. Zonder categorie worden ze alle drie getoond. |
| `modus` | `inline` (standaard) of `iframe` voor de oorspronkelijke Rokade-weergave. |

---

## Deel 2 — Standen op een pagina zetten

<!-- alleen-web:start -->
<video src="media/video/gebruiken.mp4" controls width="880"></video>

▶︎ **Video:** [media/video/gebruiken.mp4](media/video/gebruiken.mp4) — van een lege pagina
tot het gepubliceerde resultaat, met alle keuzes uit de zijbalk.
<!-- alleen-web:eind -->

### 1. Een nieuwe pagina maken

Ga naar **Pagina's → Nieuwe pagina**.

![Een lege nieuwe pagina](media/schermafbeeldingen/gebruiken-01-nieuwe-pagina.png)

### 2. De titel instellen

Typ de titel bovenaan, bijvoorbeeld **Standen jeugd**. Die titel komt op de pagina en in het
menu terecht; de standen zelf hebben geen eigen kop nodig.

![De titel van de pagina](media/schermafbeeldingen/gebruiken-02-titel.png)

### 3. Het blok invoegen

Klik linksboven op **+** (het invoegmenu), zoek op *Rokade* en kies **Rokade standen**.

![Het blok Rokade standen in het invoegmenu](media/schermafbeeldingen/gebruiken-03-blok-invoegen.png)

Het blok toont meteen een voorbeeld van wat bezoekers zullen zien. In de editor zijn de
knoppen in dat voorbeeld bewust niet aanklikbaar.

![Het blok staat op de pagina](media/schermafbeeldingen/gebruiken-04-blok-toegevoegd.png)

### 4. De instellingen van het blok

Selecteer het blok en open rechts de zijbalk (**Blok → Standen instellen**). Daar staan vier
keuzes.

![Het paneel Standen instellen](media/schermafbeeldingen/gebruiken-05-paneel.png)

#### Bron

Kies uit welke export deze pagina put. Deze keuze verschijnt alleen als er meer dan één
bronpad is ingesteld.

| Keuze | Betekenis |
| --- | --- |
| **Eerste bron (…)** | Volgt de bovenste regel uit de instellingen. Verandert die volgorde, dan verandert deze pagina mee. |
| **Jeugd** | De export met de jeugdcompetities. |
| **Senioren** | De tweede export, met een eigen seizoenenlijst. |

Voor dit voorbeeld: **Jeugd**.

![Bron op Jeugd](media/schermafbeeldingen/gebruiken-06-bron-jeugd.png)

Seizoen en competitie horen bij de gekozen bron. Kies je een andere bron, dan vallen keuzes
die daar niet bestaan automatisch terug op de standaardwaarde.

#### Seizoen

| Keuze | Betekenis |
| --- | --- |
| **Meest recente seizoen** | Toont altijd het nieuwste seizoen van deze bron. Komt er een nieuwe export bij, dan schuift de pagina vanzelf mee. |
| Een seizoen, bijvoorbeeld **2026-2027** | Toont precies dat seizoen. Handig voor een archiefpagina. |

Voor dit voorbeeld: **Meest recente seizoen**, zodat de pagina actueel blijft zonder dat
iemand hem hoeft bij te werken.

![De keuze voor het seizoen](media/schermafbeeldingen/gebruiken-07-seizoen.png)

#### Competitie

| Keuze | Betekenis |
| --- | --- |
| **Alle competities** | Toont elke soort die in dit seizoen voorkomt, met knoppen erboven om te wisselen. |
| **Interne competitie** | Alleen de interne competitie. |
| **Doorgeefschaak** | Alleen doorgeefschaak — verschijnt als het seizoen die bevat. |
| **Snelschaken** | Alleen snelschaken — idem. |

De lijst volgt wat er in het gekozen seizoen zit; een seizoen zonder snelschaken biedt die
keuze niet aan. Voor dit voorbeeld: **Alle competities**.

![De keuze voor de competitie](media/schermafbeeldingen/gebruiken-08-competitie.png)

#### Weergave

| Keuze | Betekenis |
| --- | --- |
| **Inline (in de pagina)** | De plugin leest het ranglijstdeel veilig in en geeft het de lettertypes, kleuren en links van het thema. Namen en details wisselen alleen het inhoudsvak. Standaard. |
| **Oorspronkelijke Rokade-weergave** | Toont het exportbestand zoals Rokade het maakt, in een afgeschermd kader. Kies dit als de originele opmaak belangrijker is dan de stijl van de site. |

![De oorspronkelijke Rokade-weergave](media/schermafbeeldingen/gebruiken-09-weergave-iframe.png)

![De inline weergave](media/schermafbeeldingen/gebruiken-10-weergave-inline.png)

De instellingen voor dit voorbeeld bij elkaar:

![De vier keuzes samen](media/schermafbeeldingen/gebruiken-11-instellingen-samen.png)

### 5. Publiceren

Klik rechtsboven op **Publiceren** en bevestig. Daarna kun je de pagina meteen bekijken.

![Publiceren](media/schermafbeeldingen/gebruiken-12-publiceren.png)

---

## Het resultaat

Zo ziet de pagina eruit voor bezoekers: de titel van de pagina, daaronder de standen met
knoppen in de stijl van het thema.

![De gepubliceerde pagina](media/schermafbeeldingen/resultaat-01-pagina.png)

**Soort competitie.** Staan er meerdere soorten in het seizoen, dan staat daar bovenaan een
knop voor.

![Knoppen per soort competitie](media/schermafbeeldingen/resultaat-02-soorten.png)

**Groep of blok.** Daaronder staat, onder het kopje van de periode, een knop per groep — in
de volgorde die je bij de instellingen hebt opgegeven.

![Knoppen per groep](media/schermafbeeldingen/resultaat-03-groepen.png)

**Ranglijst, kruistabel, scoretabel.** Bevat de export een kruistabel of scoretabel, dan
verschijnen deze als tabbladen boven de tabel. Het actieve tabblad sluit visueel aan op de
bijbehorende tabel. De weergaven laden in hetzelfde vak; de rest van de pagina blijft staan.

![Weergaveknoppen](media/schermafbeeldingen/resultaat-04-weergaveknoppen.png)

![De kruistabel](media/schermafbeeldingen/resultaat-05-kruistabel.png)

**Details per speler.** Een naam in de ranglijst opent de detailpagina van die speler in
hetzelfde vak.

![Het detail van een speler](media/schermafbeeldingen/resultaat-06-detail.png)

Met **← Terug naar ranglijst** ga je weer naar de stand. Deze onderstreepte link staat los van de tabel, zodat hij gemakkelijk te herkennen is als terugactie.

![De teruglink](media/schermafbeeldingen/resultaat-07-terugknop.png)

---

## Als er iets niet klopt

| Wat je ziet | Wat er meestal aan de hand is |
| --- | --- |
| “Er zijn geen leesbare standen gevonden” | Er is nog geen bronpad ingesteld, of het pad bestaat niet. Controleer onder **Instellingen → Rokade Standen** of er bij de bron seizoenen worden gevonden. |
| `0 seizoenen gevonden` | Het pad wijst niet naar de map mét de seizoensmappen, of de webserver mag de map niet lezen. Controleer ook of er per competitie een `C*Index.htm` én een bijbehorend `C*Ranglijst.htm` staat. |
| Een nieuwe export is nog niet zichtbaar | De cache staat er nog. Klik op **Nu opnieuw indexeren**. |
| Een nieuw seizoen staat niet in het blok | Het blok vult zijn keuzelijsten uit de index. Ververs de index en herlaad daarna de editor. |
| Een groep staat op een rare plek | De titel van het exportbestand komt niet overeen met een regel bij **Groepen en volgorde**. Vul de groep daar aan, of gebruik `zoekterm \| knopnaam`. |
| Het blok toont niets meer na het wijzigen van de bronnen | Een bron die uit de instellingen verdwijnt, stopt direct met laden. Het blok valt dan terug op de standaardkeuze; kies bron en seizoen opnieuw. |

---

<!-- alleen-web:start -->
## Beelden opnieuw maken

De schermafbeeldingen en video's in deze handleiding zijn opgenomen door een scenario dat de
hele route naspeelt in de lokale Docker-omgeving. Na een nieuwe feature neem je ze opnieuw op
in plaats van ze bij te werken:

```sh
docker compose up -d
cd tools/handleiding
npm run handleiding   # opnemen en de PDF bijwerken
npm run controleer    # tekst, beelden, video en PDF tegen elkaar houden
```

De werkwijze, de knoppen en het toevoegen van een stap staan in
[`tools/handleiding/README.md`](../../tools/handleiding/README.md).
`media/opname.json` legt bij elke opname vast met welke versies er is opgenomen, welke
stappen de video toont (met de seconde erbij) en welke keuzes de dropdowns aanboden — zo zie
je na een wijziging meteen welk stuk tekst nog klopt.
<!-- alleen-web:eind -->
