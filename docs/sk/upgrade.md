---
title: Návod na upgrade
description: Nekompatibilné zmeny medzi hlavnými verziami Nette API od 1.x po 3.x, ako upraviť kód a čo má zmeniť plánovaná verzia 4.0. Návod sa vedie po anglicky.
---

::: info
Návod na upgrade sa vedie po anglicky, rovnako ako v repozitári.
:::

<!--@include: ../../UPGRADE.md-->

## Časté otázky

### Existuje návod pre 2.x na 3.0?

Samostatný nie. Nekompatibilné zmeny 3.0.0 sú položky [BC] v [changelogu](/sk/changelog): `DefaultHandler` vracia `404` namiesto `400`, `ApiDecider` dostane DI kontajner a output configurator aj `error handler` treba zaregistrovať ako služby.

### Existuje 4.0, na ktorú sa dá prejsť?

Zatiaľ nie. 3.x je aktuálna rada. Na GitHube je otvorený [milník 4.0.0](https://github.com/tomaj/nette-api/milestone/9) s plánovanými nekompatibilnými zmenami, napríklad odstránením nebezpečného režimu CORS `'auto'` a vyššou minimálnou verziou PHP. Dátum vydania zatiaľ nie je; viac vo [Verziách tejto dokumentácie](/sk/guide/versioning).

### Akú verziu PHP potrebuje 3.x?

PHP 8.3 alebo novšie, pozrite si [Začíname](/sk/guide/getting-started#poziadavky).
