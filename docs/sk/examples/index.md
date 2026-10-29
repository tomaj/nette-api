---
title: Príklady
description: 'Štrnásť spustiteľných projektov Nette API testovaných v CI: parametre, JSON telo, súbory, stránkovanie, autorizácia, limity, CORS, verzie, OpenAPI a logovanie.'
---

# Príklady

Každý príklad je malá Nette aplikácia v [`examples/`](https://github.com/tomaj/nette-api/tree/master/examples). Jej `e2e.sh` spustí vstavaný PHP server a curl-om skontroluje každú odpoveď; CI ich všetky spúšťa proti aktuálnemu kódu knižnice.

```bash
git clone https://github.com/tomaj/nette-api.git
cd nette-api/examples/hello-world
composer install
./e2e.sh
```

## Verzia knižnice

Príklady vyžadujú `tomaj/nette-api` `^3.6`, takže `composer install` nainštaluje posledné vydanie 3.x. Niektoré používajú funkcie pridané vo verzii 3.6.0, napríklad maskované hlavičky v príklade [Logovanie a chyby](/sk/examples/logging-errors).

Ak chcete príklad spustiť proti lokálnemu klonu ako CI, použite z koreňa repozitára `examples/ci/example.sh hello-world`.

<LinkCards :items="[
  { title: 'Hello world', text: 'Jeden GET endpoint, najmenšie funkčné nastavenie.', link: '/sk/examples/hello-world' },
  { title: 'Parametre a validácia', text: 'Query parametre, povolené hodnoty, viac hodnôt a JSON telo so schémou.', link: '/sk/examples/params-validation' },
  { title: 'Autorizácia a obmedzenie požiadaviek', text: 'Bearer token, API kľúč, HTTP Basic a 3 požiadavky za minútu.', link: '/sk/examples/auth' },
  { title: 'CORS', text: 'Pevný origin a preflight pre frontend v prehliadači.', link: '/sk/examples/cors' },
  { title: 'OpenAPI a konzola', text: 'OpenAPI JSON a YAML, zoznam endpointov, webová konzola, {apiLink}.', link: '/sk/examples/openapi-console' },
  { title: 'Logovanie a chyby', text: 'Log požiadaviek s maskovanými hlavičkami (potrebuje 3.6.0), vlastný error handler, produkčný režim.', link: '/sk/examples/logging-errors' },
  { title: 'Problem Details', text: 'Chyby podľa RFC 9457 pre 400, 401, 404, 409, 429 a 500, výber podľa Accept, schéma v OpenAPI (potrebuje 4.0.0).', link: '/sk/examples/problem-details' },
  { title: 'Vlastná autorizácia', text: 'Vlastný ApiAuthorizationInterface s rolami, používateľ v handleri, token alebo API kľúč.', link: '/sk/examples/custom-authorization' },
  { title: 'JSON a surové telo', text: 'JSON telo overené schémou a CSV telo.', link: '/sk/examples/json-body' },
  { title: 'Nahrávanie súborov', text: 'Jeden aj viac súborov, overená veľkosť a skutočný typ.', link: '/sk/examples/file-upload' },
  { title: 'Stránkovanie', text: 'Typované parametre stránky, povolené hodnoty, odkazy, výstup pre každý kód.', link: '/sk/examples/pagination' },
  { title: 'Verzie API', text: 'v1 a v2 jedného endpointu, hlavičky zastarania, OpenAPI pre každú verziu.', link: '/sk/examples/versions' },
  { title: 'Obmedzenie požiadaviek', text: 'Vlastný RateLimitInterface, 3 požiadavky za hodinu na kľúč, vlastná 429.', link: '/sk/examples/rate-limit' },
  { title: 'Testovanie', text: 'PHPUnit testy handlera, parametrov, výstupov a autorizácie.', link: '/sk/examples/testing' },
]" />

Všetky príklady potrebujú PHP 8.3 alebo novšie a Composer. Ich `composer.json` požaduje `tomaj/nette-api: ^3.6`.

## Časté otázky

### Ktorým príkladom začať?

[Hello world](/sk/examples/hello-world), potom tým, ktorý pokrýva funkciu, ktorú potrebujete.

### Používajú príklady databázu?

Nie, dáta sú v pamäti alebo v súboroch, takže jediné nastavenie je `composer install`.

### Môžem príklad skopírovať ako základ?

Áno, konfigurácia, router a bootstrap tvoria kompletnú aplikáciu. Čo ktorá časť robí, vysvetľuje [Začíname](/sk/guide/getting-started).

### Ako ich testuje CI?

`examples/ci/example.sh NAME` skopíruje projekt, nasmeruje Composer na checkoutnutú knižnicu a spustí `e2e.sh`.

### Ktoré porty používajú?

8081 až 8087 a 8090 až 8096, jeden na príklad (logovanie a chyby používa dva), takže môžu bežať vedľa seba.
