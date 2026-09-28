# Ontwikkeling

## Lokaal starten met OrbStack/Docker

```sh
docker compose up -d
```

De geanonimiseerde demo-export staat al in `demo/`, dus een clone is direct klaar voor lokaal gebruik. Open <http://localhost:8080>, voltooi de WordPress-installatie en activeer **Rokade Standen**. Vul op **Instellingen → Rokade Standen** als bronpaden in:

```
/standen | Jeugd
/senioren | Senioren
```

Bronpaden tellen vanaf de WordPress-map (`/var/www/html` in de container). `demo/jeugd` is read-only gemount als `/var/www/html/standen` en `demo/senioren` als `/var/www/html/senioren`. Een extra bron toevoegen betekent een mountregel in `docker-compose.yml` en een regel in dit veld.

De demo bevat alleen HTML en assets die de plugin nodig heeft; de spelersnamen zijn consistente pseudoniemen. Vernieuw de demo na een nieuwe export met `node tools/anonymize-demo.mjs` voordat je hem commit.

`themes/` wordt read-only gemount naar `wp-content/themes/`. De testinstallatie gebruikt `hoogland-2023-dev` als child theme van `hoogland`; beide worden meegecommit.

Stop de omgeving met `docker compose down`; database en WordPress-installatie blijven in de Docker-volumes bewaard.

## Handleiding opnemen

De schermafbeeldingen en video's in [`docs/handleiding/`](handleiding/README.md) worden opgenomen door het scenario in [`tools/handleiding/`](../tools/handleiding/README.md), zodat ze na een nieuwe feature in één opdracht opnieuw te maken zijn.

## Productiepakket maken

```sh
npm run package
```

Het pakket verschijnt als `dist/rokade-standings-<versie>.zip` en bevat alleen de plugincode, assets en README.

## Releases en automatische updates

Elke push naar `main` draait [`.github/workflows/release.yml`](../.github/workflows/release.yml): PHP-lint, hetzelfde pakket als `npm run package`, bewaard als build-artifact. Een pull request doorloopt dezelfde stappen zonder te publiceren.

Staat er in `rokade-standings.php` een versie waarvoor nog geen tag `v<versie>` bestaat, dan publiceert de workflow een GitHub-release met de zip en `update.json`. Een release uitbrengen is dus het versienummer in de header én in `ROKADE_STANDINGS_VERSION` ophogen en naar `main` pushen. Een verlaagd versienummer wordt geweigerd.

Door de header `Update URI: https://github.com/jvanoostveen/rokade-standings` vraagt WordPress updates op bij [`Rokade_Standings_Updater`](../includes/class-rokade-standings-updater.php) in plaats van bij wordpress.org. Die leest de feed op:

```
https://github.com/jvanoostveen/rokade-standings/releases/latest/download/update.json
```

Het antwoord wordt twaalf uur bewaard; **Opnieuw controleren** wist die cache. Een mislukte controle (404, netwerkstoring, ongeldige JSON, een pakket-URL buiten GitHub) geeft nooit een foutmelding: WordPress ziet dan geen nieuwe versie en de mislukking wordt een uur onthouden.

De feed is lokaal te bouwen met `npm run manifest`.
