---
title: Časté otázky
description: Odpovede na časté otázky o Nette API - 404 pre zaregistrované endpointy, 500 pre správne odpovede, tokeny, CORS, logovanie, OpenAPI a upgrade.
---

# Časté otázky

## Prečo môj zaregistrovaný endpoint vracia 404?

Metóda, verzia, balík a akcia požiadavky sa musia presne zhodovať s `EndpointIdentifier` a routa musí viesť na `Api:Api:default`. Pozrite si [Začíname](/sk/guide/getting-started).

## Môj handler vracia správne dáta, ale klient dostane 500. Prečo?

Odpoveď nezodpovedala žiadnemu deklarovanému výstupu a validácia výstupov je zapnutá. Pozrite sa na `detail`, alebo si prečítajte [Výstupy](/sk/guide/outputs#kedy-sa-vystupy-validuju).

## Prečo produkcia hovorí len „wrong input"?

Detaily chýb sú v produkcii vypnuté, aj pre `400`. Pozrite si [Spracovanie chýb](/sk/guide/errors#detaily-chyb).

## Ako ochránim endpoint tokenom?

Použite `BearerTokenAuthorization` alebo triedu pre API kľúč s repozitárom tokenov. Pozrite si [Autorizáciu](/sk/guide/authorization).

## Prečo moja aplikácia v prehliadači dostáva CORS chybu?

Nastavte povolený origin a zapnite preflight. Pozrite si [CORS](/sk/guide/cors).

## Prečo v logu nie sú odpovede 401 a 429?

V 3.x presenter skončí pred loggerom. Pozrite si [Logovanie](/sk/guide/logging#co-sa-loguje).

## Ako publikujem OpenAPI?

Zaregistrujte `OpenApiHandler` ako endpoint. Pozrite si [OpenAPI](/sk/guide/openapi).

## Ako prejdem z 2.x?

Postupujte podľa [návodu na upgrade](/sk/upgrade) a [changelogu](/sk/changelog).

## Je k dispozícii kompletný projekt, ktorý si môžem spustiť?

Áno, 13, testovaných v CI: pozrite si [Príklady](/sk/examples/).
