---
title: DI rozšírenie
description: 'Nastavenie Nette API v jednej neon sekcii cez NetteApiExtension: endpointy, autorizácia, obmedzenie požiadaviek, CORS, logovanie a formát chýb, skontrolované pri kompilácii kontajnera.'
---

# DI rozšírenie

::: tip Od verzie 4.0.0
`Tomaj\NetteApi\DI\NetteApiExtension` je novinka vo verzii 4.0.0. Nastavenie cez `services:` zo [Začíname](/sk/guide/getting-started) funguje ďalej, rozšírenie je nepovinné.
:::

Rozšírenie zaregistruje všetko, čo API potrebuje, a endpointy načíta z jednej sekcie `api:`. Namiesto zoznamu volaní `addApi()` s dlhými názvami tried napíšete, čo endpoint je:

```yaml [config/api.neon]
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	endpoints:
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler}
```

To je celé API. `GET /api/v1/hello` zavolá `App\Api\HelloHandler` zo [Začíname](/sk/guide/getting-started#_4-napiste-handler). Stále potrebujete API routu z [kroku 2 v Začíname](/sk/guide/getting-started#_2-nasmerujte-api-na-apipresenter). `application: mapping` pre modul `Api` pridá rozšírenie; ak už nejaké máte, ostane vaše.

Každú chybu nájde Nette pri kompilácii kontajnera, nie až keď klient zavolá endpoint: neznámu triedu handlera, preklep v názve autorizácie, ten istý endpoint dvakrát. Chybová správa povie, ktorý endpoint a čo zmeniť.

## Čo rozšírenie zaregistruje

| Služba | Trieda | Zmeníte cez |
| --- | --- | --- |
| `api.decider` | `ApiDecider` | `api.endpoints` |
| `api.presenter` | `ApiPresenter` | `api.cors`, `api.logging` |
| `api.outputConfigurator` | `EnvConfigurator` | `api.outputConfigurator` |
| `api.errorHandler` | `DefaultErrorHandler` | `api.errors` |
| `api.ipDetector` | `IpDetector` | `api.ipDetector` |
| `api.logger` | iba keď ho nastavíte | `api.logger` |

S prázdnou sekciou `api:` dostanete žiadne endpointy, žiadne CORS hlavičky, formát chýb z 3.x a detaily chýb okrem `APP_ENV=production`. To je nastavenie `services:` zo Začíname, len Začíname používa `DebuggerConfigurator` (dostanete ho cez `outputConfigurator: debugger`) a presenter tam posiela `Access-Control-Allow-Origin: *` (dostanete ho cez `cors: {mode: '*'}`).

::: info Vaše služby majú prednosť
Ak už sekcia `services:` má `ApiDecider`, `ApiPresenter`, error handler, output configurator alebo IP detector, rozšírenie použije ten váš a pridá doň svoje nastavenie. Na rozšírenie tak môžete prejsť po jednom endpointe, pozrite [Prechod zo services](#prechod-zo-services).
:::

## Endpointy

Každá položka `api.endpoints` je jeden endpoint:

| Kľúč | Povinný | Význam |
| --- | --- | --- |
| `method` | áno | HTTP metóda, `GET`, `post`, ... (na veľkosti písmen nezáleží) |
| `version` | áno | `1`, `2`, `'2.1'` bez `v`; URL je `/api/v{version}/...`. Verziu s bodkou píšte v úvodzovkách, neon číta `2.10` ako číslo `2.1` |
| `package` | áno | prvá časť URL za verziou, bez lomky |
| `action` | nie | druhá časť, `/api/v1/users/detail` má akciu `detail`, bez lomky |
| `handler` | áno | handler, pozrite nižšie |
| `authorization` | nie | kto ho smie volať, pozrite [Autorizácia](#autorizacia-a-obmedzenie-poziadaviek) |
| `rateLimit` | nie | ako často, pozrite [Autorizácia](#autorizacia-a-obmedzenie-poziadaviek) |

```yaml
api:
	endpoints:
		# GET /api/v1/users
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}
		# GET /api/v1/users/detail?id=5
		- {method: GET, version: 1, package: users, action: detail, handler: App\Api\UserDetailHandler}
		# POST /api/v1/users
		- {method: POST, version: 1, package: users, handler: App\Api\UserCreateHandler}
		# GET /api/v2.1/users
		- {method: GET, version: '2.1', package: users, handler: App\Api\V2\UsersListHandler}
```

Endpointy sa zaregistrujú v tomto poradí, v ktorom sú aj vo [výpise endpointov](/sk/guide/openapi) a vo [webovej konzole](/sk/guide/console).

Rovnaká metóda, verzia, package a akcia dvakrát zastaví kompiláciu:

```
Endpoint GET /v1/users is defined twice: in api.endpoints[0] and in api.endpoints[3]. Keep only one of them.
```

### Handler

Handler môžete zapísať tromi spôsobmi:

```yaml
api:
	endpoints:
		# 1. trieda: rozšírenie ju vytvorí s autowiringom
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}

		# 2. trieda s argumentmi: statement, ako v services:
		- {method: GET, version: 1, package: export, handler: App\Api\ExportHandler(%tempDir%/exports)}

		# 3. služba zo sekcie services:
		- {method: GET, version: 1, package: reports, handler: @reportsHandler}

services:
	reportsHandler: App\Api\ReportsHandler(%tempDir%/reports)
```

- **Trieda** sa stane službou, takže jej konštruktor dostane iné služby autowiringom, rovnako ako každá iná služba. Jedna trieda použitá vo viacerých endpointoch je jedna služba. Keď sekcia `services:` už má autowirovanú službu presne tejto triedy, rozšírenie použije ju.
- **Statement** `Trieda(argumenty)` je nová služba iba pre tento endpoint.
- **`@nazov`** je služba s týmto názvom. Píšte ju bez úvodzoviek, `'@nazov'` v úvodzovkách je v neone obyčajný reťazec.

Handlery sa vytvárajú až pri zavolaní ich endpointu, takže API so stovkou endpointov nevytvára pri každej požiadavke sto objektov.

## Autorizácia a obmedzenie požiadaviek

Autorizácie a obmedzenia požiadaviek pomenujte raz a v endpointoch použite názvy:

```yaml
parameters:
	adminPassword: change-me
	apiTokens:
		secret-token: '*'

api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
		admin: Tomaj\NetteApi\Authorization\BasicAuthentication([admin: %adminPassword%])
	rateLimits:
		strict: App\FileRateLimit(storageDir: %tempDir%, limit: 2)

	endpoints:
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler, authorization: token}
		- {method: DELETE, version: 1, package: users, handler: App\Api\UserDeleteHandler, authorization: admin, rateLimit: strict}
```

`authorization` a `rateLimit` endpointu prijímajú:

| Hodnota | Význam |
| --- | --- |
| názov z `api.authorizations` / `api.rateLimits` | ten objekt, spoločný pre všetky endpointy s týmto názvom |
| `@sluzba` | služba zo `services:` |
| trieda alebo `Trieda(argumenty)` | nový objekt pre tento endpoint |
| `false` | žiadna, aj keď je nastavená predvolená |
| nič | predvolená z `api.defaults`, inak žiadna autorizácia a žiadne obmedzenie |

### Predvolené hodnoty

Väčšina API chráni skoro každý endpoint. Nastavte autorizáciu a obmedzenie požiadaviek raz v `api.defaults` pre všetky endpointy a zmeňte ich iba tam, kde endpoint potrebuje niečo iné:

```yaml
api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
		admin: Tomaj\NetteApi\Authorization\BasicAuthentication([admin: %adminPassword%])
	rateLimits:
		normal: App\Api\MyRateLimit(limit: 100)
		strict: App\Api\MyRateLimit(limit: 5)
	defaults:
		authorization: token
		rateLimit: normal
	endpoints:
		# token a normal z predvolených hodnôt
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}
		# admin namiesto token, obmedzenie ostáva normal
		- {method: DELETE, version: 1, package: users, handler: App\Api\UserDeleteHandler, authorization: admin}
		# verejný a s prísnym obmedzením
		- {method: POST, version: 1, package: login, handler: App\Api\LoginHandler, authorization: false, rateLimit: strict}
		# verejný a bez obmedzenia
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler, authorization: false, rateLimit: false}
```

O každom z dvoch kľúčov sa rozhoduje samostatne: endpoint bez kľúča použije predvolenú hodnotu, názov alebo trieda ju nahradí, `false` ju vypne. Samotné `api.defaults` hodnotu `false` neprijme, keď predvolenú hodnotu nechcete, kľúč vynechajte. Ako predvolenú hodnotu použite názov z `api.authorizations` / `api.rateLimits`, aby všetky endpointy zdieľali jeden objekt; názov triedy tam vytvorí nový objekt pre každý endpoint.

Kompiláciu zastaví aj trieda nesprávneho typu, napríklad trieda handlera použitá ako autorizácia:

```
Class 'App\Api\HelloHandler' in api.authorizations.token must implement Tomaj\NetteApi\Authorization\ApiAuthorizationInterface.
```

Preklep v názve zastaví kompiláciu a vypíše názvy, ktoré ste definovali:

```
Unknown value 'tokne' in the authorization of endpoint GET /v1/users (api.endpoints[0]). Use a name defined in the api section, @serviceName, a class name or a statement like App\MyClass(arguments). Defined names: 'token'.
```

Triedy, ktoré môžete použiť, sú na stránkach [Autorizácia](/sk/guide/authorization) a [Obmedzenie požiadaviek](/sk/guide/rate-limit).

## Chyby

`api.errors.format` vyberá [formát chýb](/sk/guide/errors):

```yaml
api:
	errors:
		# default: {"status":"error","message":"..."}, formát z 3.x
		# problemDetails: RFC 9457 application/problem+json pre každú chybu
		# negotiating: problem details iba pre klientov, ktorí pošlú Accept: application/problem+json
		format: problemDetails
		typeBaseUri: https://api.example.com/problems/
```

`typeBaseUri` je začiatok `type` problému (`https://api.example.com/problems/not-found`), pozrite [Problem Details](/sk/guide/problem-details). Funguje iba s `problemDetails` a `negotiating`.

Vlastný error handler patrí do `handler`:

```yaml
api:
	errors:
		handler: App\Api\MyErrorHandler
```

## Output configurator

`api.outputConfigurator` rozhoduje, či chybové odpovede ukazujú správu výnimky a či sa validujú výstupy, pozrite [Výstupy](/sk/guide/outputs):

| Hodnota | Trieda |
| --- | --- |
| nič alebo `env` | `EnvConfigurator`: detaily okrem `APP_ENV=production` |
| `debugger` | `DebuggerConfigurator`: detaily, keď je Tracy vo vývojovom režime |
| `query` | `QueryConfigurator`: detaily s `?error_detail=1` |
| trieda, `Trieda(argumenty)` alebo `@sluzba` | váš vlastný `ConfiguratorInterface` |

```yaml
api:
	outputConfigurator: Tomaj\NetteApi\Output\Configurator\EnvConfigurator('APP_ENV', 'production')
```

## CORS

```yaml
api:
	cors:
		# originy, ktoré smú volať API z prehliadača
		allowedOrigins: [https://app.example.com, 'http://localhost:3000']
		# poslať im Access-Control-Allow-Credentials: true (cookies)
		allowCredentials: true
		# odpovedať na OPTIONS pre každý endpoint
		preflight: true
```

| Kľúč | To isté ako | Predvolene |
| --- | --- | --- |
| `mode` | `setCorsHeader()`: `'*'`, `'off'`, `'auto'` alebo jeden origin | `'off'` |
| `allowedOrigins` | `setCorsAllowedOrigins()` | nenastavené |
| `allowCredentials` | druhý argument `setCorsAllowedOrigins()`, potrebuje `allowedOrigins` | `false` |
| `preflight` | `enableGlobalPreflight()`: `true` alebo mapa hlavičiek pre `CorsPreflightHandler` | `false` |

```yaml
api:
	cors:
		mode: 'off'
		preflight:
			Access-Control-Allow-Headers: [Content-Type, Authorization]
			Access-Control-Max-Age: '600'
```

Bez `mode` rozšírenie neposiela žiadne CORS hlavičky, na rozdiel od samotného `ApiPresenter`, ktorý posiela `*`. Prehliadačová aplikácia na inom origine potrebuje `allowedOrigins` alebo `mode`. Ak si v `services:` zaregistrujete vlastný `ApiPresenter` so `setCorsHeader()`, rozšírenie túto hodnotu nechá. Čo ktorý režim posiela, vysvetľuje stránka [CORS](/sk/guide/cors).

## Logovanie

```yaml
api:
	logger: App\Api\DatabaseLogger
	logging:
		# true: Authorization, Cookie, X-Api-Key, ... sa zalogujú ako ***
		maskHeaders: true
```

`maskHeaders` prijme aj vlastný zoznam, `[Authorization, X-Secret]`. Pozrite [Logovanie](/sk/guide/logging).

## IP detektor

```yaml
api:
	ipDetector: App\Api\ProxyIpDetector
```

Predvolený `IpDetector` číta `REMOTE_ADDR`.

## Prehľad nastavení

Všetky kľúče s predvolenými hodnotami:

```yaml
api:
	outputConfigurator: null   # env | debugger | query | Trieda | Trieda(args) | @sluzba
	ipDetector: null           # Trieda | Trieda(args) | @sluzba, predvolene IpDetector
	logger: null               # Trieda | Trieda(args) | @sluzba, predvolene bez loggera
	errors:
		format: null           # default | problemDetails | negotiating
		typeBaseUri: null      # s problemDetails alebo negotiating
		handler: null          # Trieda | Trieda(args) | @sluzba, namiesto format
	cors:
		mode: null             # '*' | 'off' | auto | origin, predvolene 'off'
		allowedOrigins: null   # zoznam originov
		allowCredentials: false
		preflight: false       # true | mapa hlavičiek
	logging:
		maskHeaders: false     # true | zoznam hlavičiek
	authorizations: []         # nazov: Trieda | Trieda(args) | @sluzba
	rateLimits: []             # nazov: Trieda | Trieda(args) | @sluzba
	defaults:
		authorization: null    # názov | Trieda | Trieda(args) | @sluzba
		rateLimit: null        # názov | Trieda | Trieda(args) | @sluzba
	endpoints: []              # {method, version, package, action, handler, authorization, rateLimit}
```

Neznámy kľúč je chyba, takže preklep ako `cors: {origins: ...}` nemôže potichu nič nerobiť.

## Prechod zo services

Rozšírenie použije služby, ktoré už máte. Pridajte ho a potom presúvajte endpointy z `addApi()` do `api.endpoints` po jednom:

```yaml
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
	- Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator
	- Tomaj\NetteApi\Error\DefaultErrorHandler
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			# ešte nepresunutý
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'old'), App\Api\OldHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())

api:
	endpoints:
		# presunutý
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler}
```

Oba endpointy skončia vo vašom `apiDecider`. Keď presuniete všetky, riadky v `services:` zmažte.

::: warning Po pridaní rozšírenia sa zmení CORS
Váš `apiPresenter` posielal `Access-Control-Allow-Origin: *`, lebo to je jeho predvolené nastavenie. S rozšírením a bez `api: cors` neposiela žiadne CORS hlavičky. Ak API volajú prehliadače z iných originov, pridajte `cors: allowedOrigins: [...]` alebo `cors: mode: '*'`, ak chcete ponechať pôvodné správanie. `setCorsHeader()` v `setup:` vášho presentera ostane.
:::

Nastaviť voľbu a mať rovnakú službu aj v `services:` je konflikt, lebo rozšírenie by nevedelo, ktorú myslíte:

```
You set api.errors, but service 'errorHandler' of type Tomaj\NetteApi\Error\ErrorHandlerInterface is registered in the services section too. Remove one of them.
```

## Časté otázky

### Musím rozšírenie používať?

Nie. Nastavenie cez `services:` z 3.x funguje bez akejkoľvek zmeny. Rozšírenie je kratší zápis toho istého.

### Prečo môj endpoint vracia 404?

Skontrolujte API routu zo [Začíname](/sk/guide/getting-started#_2-nasmerujte-api-na-apipresenter). Rozšírenie zaregistruje presenter a namapuje naň modul `Api`, ale routa je vaša. Ak má vaše `application: mapping` vlastný záznam `Api`, rozšírenie ho nechá, takže musí ukazovať na namespace `ApiPresenter`, ktorý používate (`Tomaj\NetteApi\Presenters\*Presenter`, ak nemáte vlastnú podtriedu).

### Prečo prehliadač hlási, že chýba CORS hlavička?

S rozšírením je CORS vypnutý, kým ho nenastavíte. Pridajte originy vášho frontendu do `api: cors: allowedOrigins` alebo nastavte `mode`, pozrite [CORS](#cors).

### Prečo `handler: '@myHandler'` hlási, že služba neexistuje?

V neone je `'@myHandler'` v úvodzovkách obyčajný reťazec, ktorý začína `@`. Napíšte `@myHandler` bez úvodzoviek.

### Môže handler dostať iné služby v konštruktore?

Áno. Handler zapísaný ako trieda sa vytvára s autowiringom ako každá služba. Pozrite `UserRepository` v [príklade](/sk/examples/di-extension).

### Ako spravím jeden endpoint verejný, keď je nastavená predvolená autorizácia?

Nastavte mu `authorization: false`.

### Môžem nastaviť autorizáciu a obmedzenie požiadaviek pre všetky endpointy a pre jeden ich zmeniť?

Áno. Dajte ich do `api.defaults` a `authorization` alebo `rateLimit` napíšte iba pri endpointoch, ktoré sa líšia: názov pre inú hodnotu, `false` pre žiadnu. Pozrite [Predvolené hodnoty](#predvolene-hodnoty).

### Ako zistím, čo rozšírenie vygenerovalo?

Otvorte skompilovaný kontajner v `temp/cache/nette.configurator/`. Metóda `createServiceApi__decider()` má volanie `addApi()` pre každý endpoint.

### Môže endpointy pridávať iné rozšírenie?

Áno, zavolajte `addEndpoint()` na API rozšírení vo svojom `loadConfiguration()`. Kontrola duplicít platí aj pre tieto endpointy:

```php
use Nette\DI\CompilerExtension;
use Tomaj\NetteApi\DI\EndpointDefinition;
use Tomaj\NetteApi\DI\NetteApiExtension;

final class ReportsExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        foreach ($this->compiler->getExtensions(NetteApiExtension::class) as $api) {
            $api->addEndpoint(new EndpointDefinition(
                method: 'GET',
                version: 1,
                package: 'reports',
                action: null,
                handler: \App\Api\ReportsHandler::class,
                authorization: null, // null: api.defaults, false: žiadna
                rateLimit: null,
                source: 'ReportsExtension', // zobrazí sa v chybových správach
            ));
        }
    }
}
```

V `extensions:` ho zaregistrujte za API rozšírením.

### Kde je celý projekt?

V [examples/di-extension](/sk/examples/di-extension). Jeho test `e2e.sh` beží v CI.
