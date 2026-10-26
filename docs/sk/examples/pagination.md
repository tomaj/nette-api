---
title: 'Príklad: stránkovanie'
description: 'Spustiteľný príklad Nette API s typovanými parametrami page a per_page, predvolenými a povolenými hodnotami, odkazmi prev a next a schémou pre každý kód.'
---

# Príklad: stránkovanie

`GET /api/v1/products?page=2&per_page=5` vráti jednu stránku z 23 produktov s odkazmi na predchádzajúcu a ďalšiu stránku.

<ProjectSource name="pagination" :files="['config/common.neon', 'app/Api/ProductListHandler.php', 'e2e.sh']" />

<Diagram name="examples-pagination-1" alt="page a per_page sa čítajú ako celé čísla s predvolenými hodnotami; per_page mimo 5, 10 a 20 odpovie 400; stránka za koncom odpovie 404; inak 200 s položkami a odkazmi" />

| Požiadavka | Odpoveď |
| --- | --- |
| `/api/v1/products` | `200`, stránka 1, 10 položiek, odkaz `next` |
| `?page=3&per_page=10` | `200`, 3 položky, bez `next` |
| `?per_page=7` | `400 Field contains not available value(s)` |
| `?page=0` alebo `?page=abc` | `400` z handlera |
| `?page=9` | `404`, posledná stránka je 3 |

## Handler

<<< @/../examples/pagination/app/Api/ProductListHandler.php

## Služby

<<< @/../examples/pagination/config/common.neon{yaml}

## Test

<<< @/../examples/pagination/e2e.sh{bash}

## Časté otázky

### Prečo sa page=abc dostane až do handlera?

Hodnota, ktorá nie je číslo, sa pre parameter `INTEGER` v 3.x zmení na `null` a predvolená hodnota sa vtedy nepoužije. Handler to overí. Pozrite si [Vstupné parametre](/sk/guide/params).

### Prečo má každý stavový kód výstup?

Validácia výstupov prijme odpoveď iba vtedy, keď jej kódu a schéme zodpovedá niektorý výstup. Bez výstupov pre `400` a `404` by sa tieto odpovede vo vývoji zmenili na `500`. Pozrite si [Výstupy](/sk/guide/outputs).

### Porovnávajú sa povolené hodnoty ako čísla?

`setAvailableValues([5, 10, 20])` overí hodnotu, ktorá prišla v query, takže `per_page=7` odpovie `400` ešte pred handlerom.

### Prečo odkazy v tele a nie hlavička Link?

Funguje oboje. Telo sa v konzole ľahšie číta; pre hlavičku si vložte `Nette\Http\IResponse` ako v [príklade verzií](/sk/examples/versions).

### Ako stránkovať databázu?

`per_page` použite ako `LIMIT`, `(page - 1) * per_page` ako `OFFSET` a jeden `COUNT(*)` pre celkový počet.
