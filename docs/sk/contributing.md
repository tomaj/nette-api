---
title: Prispievanie
description: 'Ako prispieť do Nette API a do tejto dokumentácie: spustenie testov a e2e kontrol príkladov, úprava diagramov a lokálny náhľad dokumentácie.'
---

::: info
Pravidlá prispievania sa vedú po anglicky, rovnako ako v repozitári. Časť o dokumentácii nižšie je po slovensky.
:::

<!--@include: ../../CONTRIBUTING.md-->

## Táto dokumentácia

Web je v `docs/`, postavený na VitePress, Node 22:

```bash
cd docs
npm ci
npm run docs:dev      # http://localhost:5173/nette-api/
npm run docs:build
npm run docs:links    # kontrola odkazov v zostavenom webe
```

- Kód na stránkach príkladov pochádza z projektov v `examples/` cez `<<< @/../examples/...`; meňte projekt, nie stránku. Každý projekt má `e2e.sh`, ktorý spúšťa CI.
- Každá anglická stránka má slovenské dvojča v `docs/sk/`; `npm run check` zlyhá, keď niektoré chýba.
- Diagramy sú Excalidraw súbory v `docs/diagrams/`, vykreslené do svetlého a tmavého SVG cez `npm run diagrams`.
- Každý pull request dostane náhľad na `https://tomaj.github.io/nette-api/pr-preview/pr-<number>/`.

## Časté otázky

### Ako spustím testy?

`composer test` spustí PHPUnit. `composer coverage` navyše zapíše reporty pokrytia do `build/` (potrebuje Xdebug). Každý projekt príkladu má aj `e2e.sh`, pozrite si [Príklady](/sk/examples/).

### Musím so zmenou upraviť aj dokumentáciu?

Áno, zmena správania potrebuje upravenú dokumentáciu, po anglicky aj po slovensky.

### Ako uvidím zmenu dokumentácie pred zlúčením?

Spustite `npm run docs:dev` v `docs/`, alebo otvorte náhľad, ktorý dostane každý pull request.
