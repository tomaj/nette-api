---
title: Autorizácia
description: Ochrana endpointov Nette API pomocou bearer tokenov, API kľúčov v hlavičke, query alebo cookie, obmedzenia IP, HTTP Basic alebo vlastného ApiAuthorizationInterface.
---

# Autorizácia

Každý endpoint má objekt autorizácie, tretí argument `addApi()`. Implementuje `ApiAuthorizationInterface` s dvoma metódami: `authorized(): bool` a `getErrorMessage(): ?string`.

<Diagram name="authorization-1" alt="ApiPresenter zavolá authorized(); false vráti 401 s getErrorMessage(), true pokračuje k parametrom a handleru" />

Keď `authorized()` vráti false, `error handler` odpovie `401` s `{"status":"error","message":"..."}` a hláškou z `getErrorMessage()`. Výnimka vyhodená z `authorized()` vráti `500` s textom výnimky.

## Vstavané triedy

| Trieda | Token číta z | Konštruktor |
| --- | --- | --- |
| `NoAuthorization` | nič, prejde vždy | žiadny |
| `BearerTokenAuthorization` | `Authorization: Bearer <token>` | `(TokenRepositoryInterface, IpDetectorInterface)` |
| `HeaderApiKeyAuthentication` | hlavičky, napríklad `X-API-Key` | `(string $headerName, TokenRepositoryInterface, IpDetectorInterface)` |
| `QueryApiKeyAuthentication` | query parametra, napríklad `?api_key=` | `(string $queryParamName, TokenRepositoryInterface, IpDetectorInterface)` |
| `CookieApiKeyAuthentication` | cookie | `(string $cookieName, TokenRepositoryInterface, IpDetectorInterface)` |
| `BasicAuthentication` | hlavičky `Authorization: Basic` (v 3.x URL požiadavky, pozrite nižšie) | `(array $users, IRequest $httpRequest)` |

Tokenové triedy zdieľajú `TokenAuthorization`: token musí byť platný v repozitári a potom IP klienta musí prejsť obmedzením IP tokenu.

## Tokeny a obmedzenie IP

`TokenRepositoryInterface` má `validToken(string $token): bool` a `ipRestrictions(string $token): ?string`. `StaticTokenRepository` berie pole token => obmedzenie:

<<< @/../examples/auth/config/common.neon{yaml}

| Obmedzenie | Znamená |
| --- | --- |
| `*` alebo prázdny reťazec | ľubovoľná IP |
| `1.2.3.4` | presne táto IP |
| `1.2.3.4, 5.6.7.8` | ktorákoľvek z uvedených, oddelené čiarkami, medzerami alebo novými riadkami |
| `10.0.0.0/8` | IPv4 rozsah |
| `null` | nikdy, `Invalid IP` |

V reálnej aplikácii implementujte `TokenRepositoryInterface` nad vašou databázou.

::: warning Známe obmedzenia v 3.x
- **Detekciu IP sa dá podvrhnúť.** Vstavaný `IpDetector` uprednostňuje hlavičky `Client-IP` a `X-Forwarded-For`, ktoré môže poslať ktorýkoľvek klient. Používajte ho len za proxy, ktorá ich prepisuje, alebo vlastný detektor ako `RemoteAddrIpDetector` z [príkladu autorizácie](/sk/examples/auth). Platí to aj v 4.0: keby `IpDetector` hlavičky ignoroval, potichu by zmenil zistenú IP pre každú aplikáciu za proxy, preto to potrebuje nastavenie dôveryhodných proxy a krok s deprecation, nie zmenu v rámci upgradu závislostí. Má to zmysel v minor verzii, ktorá najprv pridá možnosť dôveryhodných proxy. Sleduje to [#234](https://github.com/tomaj/nette-api/issues/234).
- **Počíta sa len prvý rozsah.** V zozname kontrola skončí pri prvej položke s `/`, takže `10.0.0.0/8, 192.168.0.0/16` sa s druhým rozsahom nikdy nezhoduje. Dajte rozsah na koniec, alebo jeden rozsah na token.
- **BasicAuthentication číta meno z URL požiadavky**, ktorú `nette/http` 3.2 a novší nechá prázdnu, takže v 3.x tam nikdy neprejde. Napíšte triedu, ktorá číta `$request->getBasicCredentials()`. 4.0 číta hlavičku `Authorization: Basic`.
- **Číselné tokeny sa nikdy nezhodujú** v `StaticTokenRepository`: PHP zmení kľúč ako `'12345'` na celé číslo, ktoré sa s reťazcovým tokenom nikdy nerovná. Používajte kľúče s písmenom.

Od verzie 3.6.0 porovnávajú `BasicAuthentication` a `StaticTokenRepository` heslá a tokeny cez `hash_equals()`, v konštantnom čase, takže čas odpovede neprezradí, aká časť tokenu bola správna. Ktoré hodnoty sa zhodujú, je rovnaké ako predtým. Vo vlastnom [repozitári tokenov](#vlastna-autorizacia) alebo autorizácii porovnávajte tajomstvá tiež cez `hash_equals()`.
:::

## Vlastná autorizácia

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Nette\Security\User;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

final class AdminOnlyAuthorization implements ApiAuthorizationInterface
{
    public function __construct(private User $user) {}

    public function authorized(): bool
    {
        return $this->user->isInRole('admin');
    }

    public function getErrorMessage(): ?string
    {
        return 'Admins only';
    }
}
```

## Časté otázky

### Ako vyzerá telo odpovede 401?

`{"status":"error","message":"Token doesn't exists or isn't active"}` alebo iná hláška z `getErrorMessage()`. Zmeniť ju môžete vo vlastnom [`error handleri`](/sk/guide/errors).

### Môže jeden endpoint prijať bearer token aj API kľúč?

Napíšte triedu, ktorá skúsi oboje: zavolá `authorized()` na každej a zapamätá si, ktorú hlášku vrátiť. [Príklad autorizácie](/sk/examples/auth) ukazuje každú metódu na vlastnom endpointe.

### Pošle konzola môj token?

Áno, [webová konzola](/sk/guide/console) má pole pre bearer token, API kľúč v hlavičke, query aj cookie a meno s heslom pre Basic.

### Ako povedať OpenAPI, že endpoint potrebuje token?

`OpenApiHandler` prečíta triedu autorizácie a pridá zodpovedajúcu bezpečnostnú schému. Pozrite si [OpenAPI](/sk/guide/openapi).

### Kde obmedzím, ako často môže token volať API?

To je [obmedzenie požiadaviek](/sk/guide/rate-limit), nastavuje sa pre každý endpoint vedľa autorizácie.
