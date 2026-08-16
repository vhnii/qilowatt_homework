# Spotihindade rakendus

Nord Pooli päev-ette elektrihinnad Eesti hinnapiirkonnas.

---

## Tehnilised nõuded

- **PHP 8.2 või uuem.** Arendatud ja käivitatud PHP 8.5.9 peal (CLI, Windows).
- **Composer.**


### Windowsi takistus: cURL vajab sertifikaate
Windowsi PHP ei pruugi automaatselt leida usaldusväärsete CA-juursertifikaatide kogu, mistõttu võib
HTTPS-päring Eleringi poole ebaõnnestuda veaga `unable to get local issuer certificate`.  Kui see juhtub, laadi alla
[`cacert.pem`](https://curl.se/docs/caextract.html) ja määra `php.ini` failis:

```ini
curl.cainfo = "C:\php\extras\ssl\cacert.pem"
openssl.cafile = "C:\php\extras\ssl\cacert.pem"
```

Linuxis ja macOS-is kasutatakse süsteemset sertifikaadipoodi ja seda sammu pole vaja.

## Käivitamine

```
composer install
cp .env.example .env
php -S localhost:8000 -t public
```

Ava brauseris http://localhost:8000


## Seadistus

**`config/config.php`**:

| Seadistus | Väärtus | Märkus |
|---|---|---|
| `vat_rate` | `0.24` | Käibemaksumäär, hetkel 24% |
| `network_fee` | `0` | Võrgutasu s/kWh kohta |
| `seller_margin` | `0` | Müüja marginaal s/kWh kohta |
| `cache_ttl` | `3600` | Vahemälu kehtivusaeg sekundites |
| `cache_dir` | `cache/` | Vahemälu kataloog |
| `timezone` | `Europe/Tallinn` | Serveri ajavöönd |
| `region` | `ee` | Kasutatav piirkond |
| `api_base_url` | Elering | API põhiaadress |
| `http_timeout` | 5 s | API päringu ajalõpp sekundites |
| `default_window_hours` | `3` | vaikimisi kasutatav ajavahemik tundides |
| `github_repo_url` | repo link | läheb kaasa saadetavasse e-kirja |

**`.env`**:
| Seadistus | Väärtus | Märkus |
|---|---|---|
| `SMTP_HOST` | `smtp.gmail.com` | E-maili server |
| `SMTP_PORT` | `587` | E-maili serveri port |
| `SMTP_USER` | `[USERNAME]` | E-maili kasutaja |
| `SMTP_PASS` | `[PAROOL]` | E-maili kasutaja parool |
| `MAIL_TO` | `[E_MAIL]` | E-mail kellele saata |


## Valikud ja põhjendused

- Hinnaperioodi pikkused tulevad APIs olevatest andmetest, mitte eeldusest (toimivad tunni- ja veerandtunnised andmed)
- Päeva algus ja lõpp arvutatakse Europe/Tallinn ajavööndi järgi, mitte 24 tunni liitmisega, sest kellakeeramispäeval on ööpäev 23 või 25 tundi pikk (29.03 = 23h, 25.10 = 25h).
- Võrgutasud ning marginaal on konfigureeritav
- Kõige odavam aken on graafiku taustal värvitud alana, jäädes rohkem silma
- Keskmine hind arvutatakse kestusega kaalutud keskmisena, mitte lihtsalt summa / arv valemiga, sest perioodi pikkus ei pruugi olla identne.
- API-suhtlus ja hinna-arvutused on eraldi kihtidena, sest see lubab arvutusloogikat testida ilma võrguühenduseta ja API muutumine ei mõjuta hindade arvutamist.
- Kasutajaliideses on **Tailwind CSS** ja **Chart.js** laaditud CDN kaudu aja säästmiseks

---

## Mis on tegemata

Testid puuduvad täielikult.

Järgmisena teeksin järgnevat:

- `HttpClient` on `final` ja seda ei saa testis üle kirjutada — vajaks liidest.
- `index.php` renderdab suuremas osas otse, mitte `templates/` kaudu, viiksin HTML-i vaadetesse.
- Mitme piirkonna hinna kuvamine (API-st tulevad FI, LV, LT hinnad ka)
-  Poolikult avaldatud päev jääb terveks tunniks vahemällu. Tühja vastust ei
   salvestata, aga osalist salvestatakse. Seega kella 14 paiku võib leht näidata
   poolikut päeva kuni TTL-i lõpuni, lühem TTL lahendaks selle.