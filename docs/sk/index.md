---
layout: home
title: Nette API – REST API knižnica pre Nette
titleTemplate: false
description: 'Robustné REST API vo vašej Nette aplikácii: verzované endpointy, autorizácia, validované parametre a výstupy, CORS, limity požiadaviek, logovanie a OpenAPI.'
hero:
  name: Nette API
  text: Robustné REST API priamo vo vašej Nette aplikácii
  tagline: 'Pridajte do Nette kompletnú API vrstvu: verzované endpointy, autorizáciu, validované parametre aj výstupy, CORS, obmedzenie požiadaviek, logovanie a OpenAPI dokumentáciu – napojené cez Nette DI, jeden handler na endpoint.'
  actions:
    - theme: brand
      text: Začíname
      link: /sk/guide/getting-started
    - theme: alt
      text: Príklady
      link: /sk/examples/
    - theme: alt
      text: GitHub
      link: https://github.com/tomaj/nette-api
features:
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="18" y="5" width="26" height="38" rx="7"/><path class="na-ink" d="M27 16h10M27 24h10M27 32h6"/><path class="na-acc" d="M3 24h15M11 16.5l7.5 7.5-7.5 7.5"/></svg>'
    title: Handlery namiesto presenterov
    details: Každý endpoint je malá trieda s params(), outputs() a handle(). ApiDecider k nej priradí metódu, verziu a balík.
    link: /sk/guide/handlers
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="3" y="5" width="33" height="13" rx="4"/><rect class="na-soft" x="3" y="23" width="24" height="13" rx="4"/><path class="na-ink" d="M9 11.5h12M9 29.5h8"/><circle class="na-accf na-badge" cx="35" cy="35" r="10.5"/><path class="na-mark" d="M30 35.3l3.4 3.4 6.4-7.2"/></svg>'
    title: Validovaný vstup
    details: GET, POST, PUT, cookie, súbor, raw a JSON telo s typmi, povolenými hodnotami a JSON schémami. Zlý vstup vráti 400.
    link: /sk/guide/params
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><path class="na-soft" d="M21 3l17 6v12c0 10.8-7 18.8-17 23C11 40 4 31.8 4 21V9z"/><circle class="na-ink-f" cx="21" cy="18.5" r="4.5"/><rect class="na-ink-f" x="19" y="20" width="4" height="10" rx="2"/><circle class="na-accf na-badge" cx="36" cy="36" r="10"/><path class="na-mark" d="M30.5 39a5.5 5.5 0 0 1 11 0M36 39l3-3.6"/><circle class="na-mark-f" cx="36" cy="39" r="1.7"/></svg>'
    title: Autorizácia a limity
    details: Bearer tokeny, API kľúče v hlavičke, query alebo cookie, obmedzenie IP a vlastné obmedzenie požiadaviek pre každý endpoint.
    link: /sk/guide/authorization
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="3" y="3" width="27" height="35" rx="6"/><path class="na-ink" d="M12.5 11c-2.8 0-2.8 1.8-2.8 4s-1 3.2-2.2 3.2c1.2 0 2.2 1 2.2 3.2s0 4 2.8 4M20.5 11c2.8 0 2.8 1.8 2.8 4s1 3.2 2.2 3.2c-1.2 0-2.2 1-2.2 3.2s0 4-2.8 4"/><rect class="na-accf na-badge" x="17" y="22" width="28" height="23" rx="6"/><path class="na-mark" d="M23 29.5l4.5 4-4.5 4M30.5 38.5h7.5"/></svg>'
    title: OpenAPI a konzola
    details: OpenApiHandler z handlerov vytvorí OpenAPI 3 dokument. ApiConsoleControl ponúkne formulár na vyskúšanie každého endpointu.
    link: /sk/guide/openapi
---

## Ako to vyzerá

<Diagram name="architecture-1" alt="Požiadavka prejde cez ApiDecider, obmedzenie požiadaviek, autorizáciu a hlásenie chýb parametrov k handleru; výstup sa skontroluje voči schéme a zaloguje a odpoveď (200 s JSON, XML alebo iným formátom) sa vráti klientovi; chyby 429, 401 a 400 sa klientovi vrátia tiež a neznáma URL skončí ako 404 z DefaultHandlera, ktorá sa zaloguje" />

Handler je jedna trieda:

<<< @/../examples/hello-world/app/Api/HelloHandler.php

a niekoľko riadkov konfigurácie ho zaregistruje ako `GET /api/v1/hello`:

<<< @/../examples/hello-world/config/common.neon{yaml}

[Príklad hello world](/sk/examples/hello-world) spúšťa presne tento kód v CI.

## Kam ďalej

<LinkCards :items="[
  { title: 'Začíname', text: 'Inštalácia knižnice, routa a prvý endpoint.', link: '/sk/guide/getting-started' },
  { title: 'Architektúra', text: 'Čo sa deje s požiadavkou, krok za krokom.', link: '/sk/guide/architecture' },
  { title: 'Príklady', text: 'Trinásť spustiteľných projektov, testovaných pri každom pushi.', link: '/sk/examples/' },
  { title: 'Referencia', text: 'Všetky triedy a rozhrania verzie 3.x.', link: '/sk/api/reference' },
]" />

## Časté otázky

### Čo je Nette API?

Knižnica, ktorá do Nette aplikácie pridá REST API vrstvu: endpointy, validované parametre aj výstupy, autorizáciu, CORS, obmedzenie požiadaviek, logovanie a OpenAPI dokument. Pozrite si [Začíname](/sk/guide/getting-started).

### Ako zapadá do Nette aplikácie?

Cez jednu routu na jej `ApiPresenter` a služby zaregistrované v neon konfigurácii, takže handlery dostanú závislosti z Nette DI ako každá iná služba. Pozrite si [Začíname](/sk/guide/getting-started).

### Aké verzie PHP a Nette potrebuje Nette API 3.x?

PHP 8.3 alebo novšie, `nette/application` a `nette/http` 3.x a Latte 3. Celý zoznam je v časti [Začíname](/sk/guide/getting-started#poziadavky).

### Musím pre endpointy písať presentery?

Nie. Knižnica má vlastný `ApiPresenter` a váš kód je v handleroch. Pozrite si [Handlery](/sk/guide/handlers).

### Ako fungujú verzie API?

Verzia je súčasťou každého endpointu, takže `v1` a `v2` tej istej URL môžu viesť na rôzne handlery. Pozrite si [Odkazy a verzie](/sk/guide/links) a [príklad verzií](/sk/examples/versions).

### Ako ochránim endpoint?

Pri registrácii mu dajte autorizáciu: bearer token, API kľúč v hlavičke, query alebo cookie, alebo vlastnú triedu. Pozrite si [Autorizáciu](/sk/guide/authorization).

### Spomalí to každú požiadavku?

Handlery zaregistrované názvom triedy sa vytvoria z DI kontajnera až pri volaní ich endpointu. Výnimkou sú endpointy OpenAPI, zoznamu a konzoly: čítajú všetky endpointy cez `getApis()`, a tým vytvoria všetky handlery. Pozrite si [Architektúru](/sk/guide/architecture).

### Dá sa z kódu vygenerovať dokumentácia API?

Áno. Zaregistrujte `OpenApiHandler` a ten opíše každý endpoint ako OpenAPI 3.0, v JSON alebo YAML. Pozrite si [OpenAPI](/sk/guide/openapi).

### Je dokumentácia aj po anglicky?

Áno, každá stránka má anglickú verziu. Použite jazykové menu hore alebo začnite na [anglickej úvodnej stránke](/).

### Ako prejdem na 4.0?

Verzia 4.0 zatiaľ neexistuje; 3.x je aktuálna rada. Na GitHube je otvorený [milník 4.0.0](https://github.com/tomaj/nette-api/milestone/9) s plánovanými nekompatibilnými zmenami, napríklad odstránením nebezpečného režimu CORS `'auto'` a vyššou minimálnou verziou PHP. Dátum vydania zatiaľ nie je. [Návod na upgrade](/sk/upgrade) pokrýva staršie hlavné verzie.
