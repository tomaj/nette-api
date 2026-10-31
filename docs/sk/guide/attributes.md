---
title: Endpointy z atribútov
description: 'Endpointy Nette API zapísané PHP atribútmi na triede handlera: #[Get], #[Post], #[Put], #[Patch], #[Delete], #[Endpoint], #[Authorization] a #[RateLimit], ktoré nájde DI rozšírenie.'
---

# Endpointy z atribútov

::: tip Od verzie 4.0.0
Atribúty v `Tomaj\NetteApi\Attribute` sú nové vo verzii 4.0.0. Potrebujú [DI rozšírenie](/sk/guide/di-extension).
:::

S atribútmi zapíšete URL endpointu priamo na triedu handlera. V neone nie je zoznam endpointov, ktorý treba držať v súlade s kódom:

```php [app/Api/UsersListHandler.php]
namespace App\Api;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users')]
final class UsersListHandler extends BaseHandler
{
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['users' => []]);
    }
}
```

`#[Get(1, 'users')]` znamená `GET /api/v1/users`. Neon konfigurácia hovorí iba to, kde sú handlery:

```yaml [config/api.neon]
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	discovery:
		paths: [%appDir%/Api]
```

Keď Nette kompiluje kontajner, rozšírenie nájde každú triedu v `app/Api` s atribútom endpointu a zaregistruje ju, presne ako keby ste ju zapísali do `api: endpoints:`. Skompilovaný kontajner je rovnaký: jedno volanie `addApi()` na endpoint a lenivo vytváraný handler.

## Atribúty endpointov

| Atribút | Endpoint |
| --- | --- |
| `#[Get(1, 'users')]` | `GET /api/v1/users` |
| `#[Get(1, 'users', 'detail')]` | `GET /api/v1/users/detail` |
| `#[Post(1, 'users')]` | `POST /api/v1/users` |
| `#[Put(1, 'users', 'update')]` | `PUT /api/v1/users/update` |
| `#[Patch(1, 'users')]` | `PATCH /api/v1/users` |
| `#[Delete(1, 'users')]` | `DELETE /api/v1/users` |
| `#[Endpoint('HEAD', 2, 'users')]` | `HEAD /api/v2/users`, ľubovoľná metóda |

Argumenty sú rovnaké ako v `api: endpoints:`: `version`, `package` a nepovinná `action`. Verzia môže byť číslo alebo reťazec ako `'1.1'`. Fungujú aj pomenované argumenty: `#[Get(version: 1, package: 'users', action: 'detail')]`.

### Jeden handler, viac endpointov

Atribúty sa môžu opakovať. Každý je samostatný endpoint s rovnakou službou handlera:

```php
#[Get(1, 'users')]
#[Get(2, 'users')]
#[Post(1, 'users', 'search')]
final class UsersListHandler extends BaseHandler
```

## Autorizácia a obmedzenie požiadaviek

`#[Authorization]` a `#[RateLimit]` platia pre všetky endpointy triedy. Hodnota je rovnaká ako v neone: meno z `api: authorizations:` / `api: rateLimits:`, `@service`, názov triedy alebo `false` pre žiadnu. `#[RateLimit]` berie aj zoznam názvov, `#[RateLimit(['perIp', 'perUser'])]`, alebo vlastný limit, `#[RateLimit(limit: 5, interval: '1 minute')]` s voliteľnými `algorithm`, `key` a `name`; pozrite si [Obmedzenie požiadaviek](/sk/guide/rate-limit).

```php
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Attribute\RateLimit;

#[Post(1, 'users')]
#[Authorization('token')]
#[RateLimit('strict')]
final class UserCreateHandler extends BaseHandler
```

```yaml
api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
	rateLimits:
		strict: {algorithm: fixedWindow, limit: 5, interval: 1 minute}
	defaults:
		authorization: token
```

Bez atribútu dostane endpoint `api: defaults:`. S `defaults: authorization: token` teda nepíšete `#[Authorization('token')]` nikde a označíte iba verejné endpointy:

```php
#[Get(1, 'hello')]
#[Authorization(false)]
final class HelloHandler extends BaseHandler
```

::: warning Bez predvolenej hodnoty je endpoint verejný
Keď nie je nastavené `api: defaults: authorization:`, handler bez `#[Authorization]` nepotrebuje žiadnu autorizáciu, rovnako ako v `api: endpoints:`. Nastavte predvolenú hodnotu a verejné endpointy označte `#[Authorization(false)]`, aby zabudnutý atribút znamenal chránený endpoint, nie otvorený.
:::

::: info Výraz v atribúte nejde
PHP atribúty berú iba konštantné hodnoty, takže `#[Authorization('App\MyAuth(%secret%)')]` nefunguje. Dajte autorizácii meno v `api: authorizations:` a použite meno.
:::

## Kde rozšírenie hľadá

### Adresáre

`discovery: paths:` je zoznam adresárov. Rozšírenie prečíta všetky PHP súbory v nich vrátane podadresárov. Každá trieda s atribútom endpointu dostane vlastnú službu vytvorenú s autowiringom, takže jej konštruktor si môže pýtať iné služby.

`discovery: namespace:` obmedzí hľadanie na jeden namespace. Ostatné triedy v adresároch sa ani nenačítajú:

```yaml
api:
	discovery:
		paths: [%appDir%]
		namespace: App\Api
```

Triedy musí vedieť načítať composer autoloader. Trieda, ktorú nevie, zastaví kompiláciu s názvom svojho súboru.

V debug režime nový súbor v niektorom z adresárov (aj v podadresári) alebo zmenený súbor handlera prebuduje kontajner, rovnako ako zmena v neon súbore.

### Registrované služby

Rozšírenie číta atribúty aj každého autowired handlera, ktorého zaregistrujete v `services:`, aj bez `discovery:`. Takto sa rieši handler, ktorý potrebuje skalárny argument:

```yaml
services:
	- App\Api\HelloHandler('Hello')

api:
	discovery:
		paths: [%appDir%/Api]
```

`HelloHandler` má `#[Get(1, 'hello')]`. Je aj v `app/Api`, ale rozšírenie použije registrovanú službu a druhú nevytvorí.

Službu s `autowired: false` preskočí, rovnako ako pri `handler: App\Api\HelloHandler` v `endpoints:`. Trieda z `paths` potom dostane vlastnú službu. Ak chcete použiť službu bez autowiringu, uveďte jej endpointy v `api: endpoints:` s `handler: @nazov` (bez úvodzoviek).

## Atribúty a neon spolu

`api: endpoints:` a atribúty môžete kombinovať. Všetky endpointy prejdú rovnakými kontrolami: rovnaká metóda, verzia, package a akcia dvakrát zastaví kompiláciu a správa povie, kde sú oba:

```
Endpoint GET /v1/users is defined twice: in api.endpoints[0] and in #[Get] on App\Api\UsersListHandler. Remove one of them, or change the method, version, package or action of one so the URLs differ.
```

Poradie je: najprv endpointy z `api: endpoints:`, potom endpointy z atribútov zoradené podľa názvu triedy.

## Chyby

Každá správa hovorí, čo urobiť, väčšinou s príkladom. Najčastejšie:

| Správa začína | Príčina |
| --- | --- |
| `Class App\Api\Foo has an endpoint attribute, but it does not implement ...` | Atribút je na triede, ktorá nie je handler. |
| `Class App\Api\Foo has an endpoint attribute, but it is abstract` | Atribúty sa nededia, dajte ich na každý konkrétny handler. |
| `Attribute #[Get] on method App\Api\Foo::handle() is ignored` | Atribúty endpointov fungujú iba na triede. |
| `Class App\Api\Foo has #[Authorization] but no route attribute` | `#[Authorization]` a `#[RateLimit]` potrebujú na tej istej triede atribút cesty. |
| `Endpoint in #[Get] on App\Api\Foo has an invalid version 'v1'` | `v` sa do URL pridá samo, napíšte `#[Get(1, 'users')]`. |
| `Invalid endpoint attribute on App\Api\Foo (Attribute "...\Authorization" must not be repeated)` | `#[Authorization]` a `#[RateLimit]` môžu byť na triede iba raz. |
| `Class App\Foo (...) found by api.discovery cannot be loaded` | Trieda dedí alebo implementuje triedu, ktorá neexistuje. Opravte `use` alebo názov, alebo obmedzte hľadanie cez `namespace:`. |
| `Class App\Foo (...) found by api.discovery cannot be autoloaded.` | Opravte namespace alebo sekciu autoload v `composer.json`, alebo obmedzte hľadanie cez `namespace:`. |
| `api.discovery.paths[0]: directory '...' does not exist.` | Preklep v ceste. Pre cestu relatívnu k aplikácii použite `%appDir%`. |
| `api.discovery.namespace '...' matches no class in api.discovery.paths` | Preklep v namespace, správa vypíše nájdené namespacy. |
| `Services 'a' and 'b' are both of class ...` | Zaregistrujte handler iba raz. |
| `Service 'foo' (class ...) has endpoint attributes, but it is registered with autowired: false` | Atribúty služby bez autowiringu sa ignorujú, použite ju ako `handler: @foo` alebo odstráňte `autowired: false`. |
| `Unknown authorization 'tokne' in #[Authorization] on ...` | Meno nie je v `api: authorizations:`. |

## Prehľad konfigurácie

```yaml
api:
	discovery:
		# adresáre s triedami handlerov, predvolene prázdne (čítajú sa iba registrované služby)
		paths: [%appDir%/Api]
		# iba triedy v tomto namespace, predvolene null (všetky triedy)
		namespace: App\Api
```

| PHP atribút | Argumenty |
| --- | --- |
| `Get`, `Post`, `Put`, `Patch`, `Delete` | `version`, `package`, `action = null` |
| `Endpoint` | `method`, `version`, `package`, `action = null` |
| `Authorization` | `authorization`: meno, `@service`, názov triedy alebo `false` |
| `RateLimit` | `rateLimit`: meno, zoznam mien, `@service`, názov triedy alebo `false`; alebo `limit`, `interval`, `algorithm = 'slidingWindow'`, `key = 'ip'`, `name = null` |

## Rozhodnutia v návrhu

Toto sú zámerné obmedzenia. Každé má issue v backlogu s výhodami, nevýhodami a podmienkami, za ktorých by sa doplnilo. Ak niečo z toho potrebujete, napíšte tam.

### Registrované služby sa čítajú vždy

Rozšírenie číta atribúty každej autowired služby handlera, prepínač `discovery: services: false` neexistuje.

- **Prečo:** atribút na triede hovorí „sprístupni tento endpoint“, takže platí všade, kde je handler zaregistrovaný. Je to aj spôsob, ako dať handleru skalárne argumenty konštruktora.
- **Prečo nie prepínač:** ďalšia voľba pre potrebu, ktorú zatiaľ nikto nemá. Už dnes môžete službu zaregistrovať s `autowired: false` alebo atribút odstrániť.
- **Kedy by to dávalo zmysel:** knižnica dodáva handlery s atribútmi a aplikácie ich chcú zaregistrovať na iných URL. Pozri [#226](https://github.com/tomaj/nette-api/issues/226).

### Autorizácia a obmedzenie požiadaviek iba pre celú triedu

`#[Get(1, 'users', authorization: false)]` neexistuje.

- **Prečo:** jeden handler má zvyčajne jedno pravidlo prístupu. Dve pravidlá v jednej triede naznačujú, že robí dve veci, a dva handlery sa z pohľadu bezpečnosti kontrolujú ľahšie. Dve úrovne (trieda, `api: defaults:`) sú jednoduchšie ako tri.
- **Prečo nie pre každý endpoint:** atribúty ciest sa predĺžia a zabudnutý argument sa ťažšie všimne.
- **Kedy by to dávalo zmysel:** handlery, ktoré sa rozumne rozdeliť nedajú, alebo opakované požiadavky. Pozri [#227](https://github.com/tomaj/nette-api/issues/227).

### Vlastná autorizácia iba cez meno z neonu

`#[Authorization('token')]` odkazuje na `api: authorizations: token`. Výraz s argumentmi v atribúte PHP nedovolí a meno triedy (`::class`) nie je zdokumentovaný ani podporovaný zápis.

- **Prečo:** tajné hodnoty a argumenty konštruktora patria do neonu. Jedno meno sa nastaví na jednom mieste a používajú ho atribúty aj `api: endpoints:`.
- **Prečo nie `::class`:** funguje len pre triedy bez skalárnych argumentov, takže by boli dva spôsoby na to isté a bezpečnostné nastavenia by boli rozdelené medzi neon a kód.
- **Kedy by to dávalo zmysel:** veľa autorizácií bez argumentov, kde meno v neone len opakuje triedu. Pozri [#228](https://github.com/tomaj/nette-api/issues/228).

## Časté otázky

### Musím používať atribúty?

Nie. `api: endpoints:` aj nastavenie cez `services:` z 3.x fungujú ďalej. Atribúty sú spôsob, ako mať URL pri kóde.

### Prečo sa môj handler nenašiel?

Skontrolujte, že adresár je v `discovery: paths:`, trieda je v `namespace:` (ak ho nastavujete) a má atribút endpointu. Handler bez neho sa zámerne preskočí.

### Číta sa adresár pri každej požiadavke?

Nie. Rozšírenie prečíta triedy raz, pri kompilácii kontajnera. Skompilovaný kontajner má iba volania `addApi()`, rovnako ako s `api: endpoints:`.

### Môže handler dostať služby v konštruktore?

Áno, handler sa vytvára s autowiringom. Pre skalárny argument zaregistrujte handler v `services:`, pozrite [Registrované služby](#registrovane-sluzby).

### Dostane podtrieda atribúty svojho rodiča?

Nie. PHP atribúty nededí, ani z traitu či rozhrania. Atribúty píšte na výslednú triedu handlera. Atribút na abstraktnej triede, rozhraní, traite alebo enume zastaví kompiláciu.

### Dá sa nastaviť autorizácia pre jeden endpoint triedy?

Nie, `#[Authorization]` platí pre celú triedu. Rozdeľte endpointy do dvoch handlerov alebo ten výnimočný zapíšte do `api: endpoints:`. Prečo, pozri [Rozhodnutia v návrhu](#autorizacia-a-obmedzenie-poziadaviek-iba-pre-celu-triedu).

### Posiela endpoint z atribútu CORS hlavičky?

Len ak nastavíte `api: cors:`. Bez neho je režim `off` a žiadne CORS hlavičky sa neposielajú, rovnako ako pri endpointoch z neonu. Pozri [CORS](/sk/guide/cors).

### Ako zistím, ktoré endpointy sa našli?

Otvorte skompilovaný kontajner v `temp/cache/nette.configurator/` a hľadajte `addApi(`. Alebo ich vypíšte vo [webovej konzole](/sk/guide/console).

### Kde je kompletný projekt?

V [examples/attributes](/sk/examples/attributes). Jeho test `e2e.sh` beží v CI.
