---
title: CORS
description: Ako Nette API posiela Access-Control-Allow-Origin cez setCorsHeader(), zoznam setCorsAllowedOrigins() alebo callback setCorsOriginValidator() a ako odpovedá na preflight požiadavky OPTIONS.
---

# CORS

Aplikácia v prehliadači na inom origine potrebuje CORS hlavičky. `ApiPresenter` ich pridá ku každej odpovedi a `ApiDecider` vie odpovedať na preflight `OPTIONS` požiadavky.

<Diagram name="cors-1" alt="Prehliadač pošle OPTIONS, dostane Access-Control hlavičky, potom pošle POST a dostane 201 s Access-Control-Allow-Origin" />

::: danger 'auto' / 'reflect-unsafe' nie je bezpečné, použite zoznam originov
`'auto'` (vo 4.0 pod názvom `'reflect-unsafe'`) posiela späť origin z hlavičky `Referer` a povoľuje credentials, takže ľubovoľná stránka, ktorú prihlásený používateľ navštívi, môže volať API s jeho cookies a čítať odpoveď. Použite [`setCorsAllowedOrigins()`](#povolene-originy) (pribudlo vo verzii 3.6.0) alebo jeden pevný origin. `'auto'` v 3.x funguje ďalej rovnako. Vo 4.0 je premenované na `'reflect-unsafe'` ([#186](https://github.com/tomaj/nette-api/issues/186)): `setCorsHeader('auto')` aj `api: cors: mode: auto` vyhodia chybu, ktorá ukáže zoznam originov na náhradu aj ako si nechať staré správanie, a `ApiPresenter` neposiela CORS hlavičky, kým ich nenastavíte (predvolené `'off'` namiesto `'*'`).
:::

## Access-Control-Allow-Origin

Každá odpoveď dostane `Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS` a k tomu jedno z nasledujúceho podľa `ApiPresenter::setCorsHeader()`:

| Hodnota | Posielané hlavičky |
| --- | --- |
| `'*'` (predvolené v 3.x; vo 4.0 a s [DI rozšírením](/sk/guide/di-extension#cors) je predvolené `'off'`) | `Access-Control-Allow-Origin: *` |
| `'https://app.example.com'` | `Access-Control-Allow-Origin: https://app.example.com` |
| `'auto'` | schéma, host a port z hlavičky `Referer` (každý uvedený port okrem `80` ostane, teda aj `:443`) a `Access-Control-Allow-Credentials: true`; bez Referer nič. **Nebezpečné.** Vo 4.0 premenované na `'reflect-unsafe'` (ktoré navyše vždy posiela `Vary: Origin, Referer`), `'auto'` potom vyhodí chybu |
| `'off'` | žiadna `Access-Control-Allow-Origin` |

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsHeader('https://app.example.com')
```

V PHP môžete namiesto preddefinovaných reťazcov odovzdať enum `Presenters\CorsMode`: `CorsMode::Any` (`'*'`), `CorsMode::Auto` (`'auto'`; vo 4.0 `CorsMode::ReflectUnsafe`, `'reflect-unsafe'`) a `CorsMode::Off` (`'off'`).

Vo 4.0 `setCorsHeader()` prijme iba tieto režimy alebo jeden origin v tvare `scheme://host[:port]`. Čokoľvek iné (`'OFF'`, `'null'`, `'https://app.example.com/'`, zoznam) vyhodí `InvalidArgumentException` namiesto toho, aby sa poslalo ako hlavička, ktorú žiadny prehliadač neprijme.

Toto je jediná služba `apiPresenter` zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby): pridajte k nej tieto riadky `setup:`, alebo dajte blok pod rovnakým názvom do iného konfiguračného súboru a Nette ich zlúči. Druhú službu `ApiPresenter` neregistrujte; dve skončia chybou „Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found“.

## Povolené originy

::: tip Od verzie 3.6.0
Toto je odporúčané nastavenie. Na 3.5.0 namiesto toho použite jeden pevný origin.
:::

`setCorsAllowedOrigins(array $origins, bool $allowCredentials = false)` porovná hlavičku `Origin` požiadavky so zoznamom. Keď je nastavený, má prednosť pred `setCorsHeader()`.

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsAllowedOrigins(['https://app.example.com', 'http://localhost:3000'], true)
```

| Požiadavka | Posielané hlavičky |
| --- | --- |
| `Origin` je v zozname | `Access-Control-Allow-Origin` s týmto originom, `Vary: Origin` a `Access-Control-Allow-Credentials: true`, ak je druhý argument `true` |
| iný `Origin` alebo žiadny | iba `Vary: Origin`, takže prehliadač odpoveď zablokuje |

Porovnanie ignoruje veľkosť písmen. V zozname sa predvolený port vynechá, takže položka `https://app.example.com:443` zodpovedá originu požiadavky `https://app.example.com` (prehliadače predvolený port nikdy neposielajú). Každý origin musí mať tvar `scheme://host[:port]`: bez cesty, bez lomky na konci, bez `*` či `null` a medzinárodné domény v punycode. Čokoľvek iné alebo prázdny zoznam vyhodí `InvalidArgumentException`.

Pri prechode z `'auto'` vypíšte originy, ktoré API naozaj volajú (frontendy a hosty pre lokálny vývoj), a `setCorsHeader('auto')` nahraďte `setCorsAllowedOrigins([...], true)`. `true` nechajte, len ak sa spoliehate na cookies alebo `withCredentials`.

## Dynamické originy

::: tip Od verzie 4.0.0
Na 3.x pridávajte hlavičky vo webovom serveri alebo v handleri `Application::$onRequest`.
:::

Keď originy vopred nepoznáte (napríklad domény zákazníkov v databáze), o každej požiadavke rozhodne `setCorsOriginValidator(callable|CorsOriginValidatorInterface $validator, bool $allowCredentials = false)`. Dostane hlavičku `Origin` z požiadavky a vráti `true`, ak ju povoľuje:

```php
$presenter->setCorsOriginValidator(
	fn (string $origin): bool => $customerDomains->exists($origin),
	allowCredentials: true,
);
```

V neon odovzdajte triedu, ktorá implementuje `Tomaj\NetteApi\Misc\CorsOriginValidatorInterface`:

```php
namespace App\Api;

use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;

final class CustomerOrigins implements CorsOriginValidatorInterface
{
	public function __construct(private CustomerRepository $customers)
	{
	}

	public function isAllowedOrigin(string $origin): bool
	{
		return $this->customers->hasDomain($origin);
	}
}
```

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsOriginValidator(@App\Api\CustomerOrigins, true)
	- App\Api\CustomerOrigins
```

S [DI rozšírením](/sk/guide/di-extension#cors) napíšte `api: cors: {originValidator: App\Api\CustomerOrigins, allowCredentials: true}`.

| Požiadavka | Posielané hlavičky |
| --- | --- |
| validátor vráti `true` | `Access-Control-Allow-Origin` presne s týmto originom, `Vary: Origin` a `Access-Control-Allow-Credentials: true`, ak je druhý argument `true` |
| validátor vráti `false`, žiadny `Origin` alebo hodnota, ktorá nie je origin | iba `Vary: Origin`, takže prehliadač odpoveď zablokuje |

- Validátor dostane iba origin v tvare `scheme://host[:port]`, ako ho posielajú prehliadače. `null`, cesta, zoznam ani nič s medzerami sa k nemu nedostane, takže do hlavičky nepustí neplatnú hodnotu. Origin porovnávajte presne (`===`, `in_array(..., true)`), nikdy cez `str_contains()` alebo regulárny výraz bez ukotvenia: `https://example.com.evil.com` obsahuje `example.com`.
- Credentials sú povolené, lebo validátor je tiež zoznam povolených originov. Vráťte `true` len pre originy, ktorým zveríte cookies používateľa.
- Validátor beží raz pri každej požiadavke s platnou hlavičkou `Origin` (požiadavky z iného originu, ich preflight aj `POST` z rovnakého originu), pomalé vyhľadávanie si cachujte. Výnimku, ktorú vyhodí, error handler nezachytí; keď vyhľadávanie zlyhá, vráťte `false`.
- Ak je nastavený, má prednosť pred `setCorsHeader()`. Nedá sa kombinovať so `setCorsAllowedOrigins()`: volanie oboch vyhodí `LogicException`, pevné originy skontrolujte vo validátore.

Chyby skončia správou, ktorá hovorí, čo je zle a ako to opraviť: hodnota, ktorá nie je callable (napríklad zoznam originov, ten patrí do `setCorsAllowedOrigins()`), callable s viac ako jedným povinným parametrom alebo s deklarovaným návratovým typom iným ako `bool` vyhodí pri nastavení `InvalidArgumentException`. Validátor, ktorý za behu vráti niečo iné ako bool, vyhodí `UnexpectedValueException`, napríklad:

```
The CORS origin validator returned int for origin 'https://app.example.com', but it must return true (allow the origin) or false (reject it). Return a bool, for example return in_array($origin, $origins, true);
```

## Preflight požiadavky

Pred `POST` s JSON telom alebo hlavičkou `Authorization` pošle prehliadač `OPTIONS` na tú istú URL. `enableGlobalPreflight()` naň odpovie pre každý zaregistrovaný endpoint:

<<< @/../examples/cors/config/common.neon{yaml}

`CorsPreflightHandler` pridá hlavičky, ktoré mu dáte, a odpovie `200` s `[]`. Bez argumentov pošle `Access-Control-Allow-Headers: Authorization` a `X-Requested-With`. `OPTIONS` na neznámu URL stále vráti `404`.

## Časté otázky

### Je 'auto' bezpečné?

Nie pre API, ktoré používajú cookies: ľubovoľná stránka, ktorú prihlásený používateľ navštívi, môže volať API s jeho cookies a čítať odpoveď. Použite pevný origin alebo [zoznam povolených originov](#povolene-originy). Preto ho 4.0 premenúva na `'reflect-unsafe'`, aby bolo riziko vidieť tam, kde sa nastavuje.

### Môžem si vo 4.0 nechať správanie 'auto'?

Áno, ako výslovné zapnutie: `setCorsHeader('reflect-unsafe')` (alebo `CorsMode::ReflectUnsafe`, alebo `api: cors: {mode: reflect-unsafe}`) sa správa ako `'auto'` v 3.x a navyše posiela `Vary: Origin, Referer`, aby zdieľané cache nepoužili odrazený origin pre inú stránku. Starý názov `'auto'` vyhodí chybu, ktorá ukáže zoznam originov aj túto možnosť. Diera ostáva rovnaká: ľubovoľná stránka môže posielať požiadavky s cookies používateľa a čítať odpovede. Použite ho, len ak API nepoužíva cookies ani iné credentials prehliadača, alebo počas prechodu. Inak vypíšte originy cez [zoznam povolených originov](#povolene-originy). Ak originy naozaj vopred nepoznáte (napríklad domény zákazníkov v databáze), použite [validátor](#dynamicke-originy).

### Ako povolím viac originov?

Cez [`setCorsAllowedOrigins()`](#povolene-originy), alebo cez [validátor](#dynamicke-originy), keď zoznam vopred nepoznáte. Na 3.5.0, ktoré zoznam nemá, vyberte jeden origin, alebo použite `'off'` a hlavičky pridávajte vo webovom serveri.

### Preflight mi vracia 404. Prečo?

Zavolajte `enableGlobalPreflight()` a overte, že existuje endpoint s rovnakou verziou, balíkom a akciou. [Príklad CORS](/sk/examples/cors) testuje oboje.

### Potrebujem CORS pre volania server-server?

Nie, kontrolujú ho len prehliadače.

### Posiela sa CORS hlavička aj pri chybách?

Áno, pridáva sa pred všetkým ostatným, takže ju majú aj `401`, `404` a `500`. Pozrite si [Architektúru](/sk/guide/architecture).
