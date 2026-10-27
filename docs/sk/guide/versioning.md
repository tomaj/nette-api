---
title: Verzie tejto dokumentácie
description: Ktoré vydanie Nette API táto dokumentácia opisuje, ako sa označujú funkcie „Od verzie 3.6.0“ a kde je dokumentácia pre 4.x a staršiu radu 2.x.
---

# Verzie tejto dokumentácie

Tento web opisuje **3.x**, aktuálnu radu vydaní; posledné vydanie je 3.6.0. Nainštalujete ho cez `composer require tomaj/nette-api`. Potrebuje PHP 8.3 alebo novšie.

<Diagram name="versioning-1" alt="Dnes je 3.x v koreni; keď vyjde 4.x, prevezme koreň a 3.x sa presunie do /3.x/" />

| Rada | Kde |
| --- | --- |
| 3.x | tento web, v koreni |
| 4.x | po vydaní prevezme koreň; 3.x sa presunie do `/3.x/` |
| 2.x a staršie | [README verzie 2.13.0](https://github.com/tomaj/nette-api/tree/2.13.0) na GitHube |

[Changelog](/sk/changelog) obsahuje každé vydanie, [návod na upgrade](/sk/upgrade) nekompatibilné zmeny.

## Časté otázky

### Ako zistím, ktorú verziu mám?

`composer show tomaj/nette-api`.

### Bude 3.x dostávať opravy aj po 4.0?

Na GitHube je otvorený [milník 4.0.0](https://github.com/tomaj/nette-api/milestone/9) s plánovanými nekompatibilnými zmenami, napríklad odstránením nebezpečného režimu CORS `'auto'` a vyššou minimálnou verziou PHP. Dátum vydania zatiaľ nie je. Či bude 3.x dostávať opravy aj potom, zatiaľ nie je rozhodnuté; dnes idú opravy a novinky do 3.x a [changelog](/sk/changelog) ukazuje každé vydanie.

### Označujú stránky novinky novšieho vydania?

Áno, poznámka „Od verzie 3.6.0“ označuje, čo pridalo vydanie 3.6.0; staršie vydania ako 3.5.0 to nemajú, ako na stránkach [CORS](/sk/guide/cors) a [Logovanie](/sk/guide/logging).

### Kde nahlásim chybu v dokumentácii?

Otvorte issue alebo pull request, pozrite si [Prispievanie](/sk/contributing).

### Je to to isté ako verzie API v mojich URL?

Nie, tie opisujú [Odkazy a verzie](/sk/guide/links#verzie).
