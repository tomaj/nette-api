---
title: 'Príklad: autorizácia a obmedzenie požiadaviek'
description: 'Spustiteľný príklad Nette API: bearer token, hlavička X-API-Key, HTTP Basic s hash_equals, obmedzenie IP bez podvrhnutia a limit 3 požiadavky za minútu.'
---

# Príklad: autorizácia a obmedzenie požiadaviek

Štyri endpointy s rovnakým handlerom, každý chránený inak.

<ProjectSource name="auth" :files="['config/common.neon', 'app/BasicCredentialsAuthorization.php', 'app/RemoteAddrIpDetector.php', 'e2e.sh']" />

<Diagram name="examples-auth-1" alt="Štyri endpointy: bearer, api-key a basic kontrolujú prihlasovacie údaje a pri chybe vrátia 401, limited vráti 429 po troch požiadavkách" />

| Endpoint | Ochrana | Vyskúšajte |
| --- | --- | --- |
| `/api/v1/bearer` | `BearerTokenAuthorization` | `-H 'Authorization: Bearer secret-token'` |
| `/api/v1/api-key` | `HeaderApiKeyAuthentication` | `-H 'X-API-Key: secret-token'` |
| `/api/v1/basic` | `BasicCredentialsAuthorization` z príkladu | `-u admin:change-me` |
| `/api/v1/limited` | `NoAuthorization` a `FixedWindowLimiter` s `NetteCacheStorage` | štyri volania za minútu |

## Služby

<<< @/../examples/auth/config/common.neon{yaml}

`office-token` je povolený len z `10.0.0.0/8`; test ukazuje, že podvrhnutá `X-Forwarded-For` nepomôže.

## IP klienta

<<< @/../examples/auth/app/RemoteAddrIpDetector.php

Vstavaný `IpDetector` dôveruje hlavičkám klienta. Tento číta len `REMOTE_ADDR`. Pozrite si [Autorizáciu](/sk/guide/authorization#tokeny-a-obmedzenie-ip).

## HTTP Basic

<<< @/../examples/auth/app/BasicCredentialsAuthorization.php

V 3.x vstavaná `BasicAuthentication` nefunguje s `nette/http` 3.2 a novším (4.0 to opravuje); táto trieda číta hlavičku `Authorization` a porovnáva cez `hash_equals()`.

## Test

<<< @/../examples/auth/e2e.sh{bash}

## Časté otázky

### Odkiaľ sa berú skutočné tokeny?

Z vášho vlastného `TokenRepositoryInterface` nad databázou. `StaticTokenRepository` s tokenmi v NEON je pre príklady a interné nástroje.

### Ako sa obmedzenie dostane k endpointu?

`addApi()` ho dostane ako štvrtý argument, `RateLimiterInterface` sa neautowiruje. Pozrite si [Obmedzenie požiadaviek](/sk/guide/rate-limit).

### Čo hovoria hlášky 401?

Napríklad `Token doesn't exists or isn't active` alebo `Invalid IP`. Pozrite si [Autorizáciu](/sk/guide/authorization).

### Líši sa handler podľa endpointu?

Nie, `WhoAmIHandler` vráti endpoint, pre ktorý bol zavolaný, takže test vidí, ktorý bežal.

### Môžem to vyskúšať vo webovej konzole?

[Príklad OpenAPI a konzoly](/sk/examples/openapi-console) má v konzole endpoint chránený bearer tokenom.
