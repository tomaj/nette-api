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
Endpoint GET /v1/users is defined twice: in api.endpoints[0] and in api.endpoints[3]. Remove one of them, or change the method, version, package or action of one so the URLs differ.
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
		strict: {algorithm: fixedWindow, limit: 2, interval: 1 minute}

	endpoints:
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler, authorization: token}
		- {method: DELETE, version: 1, package: users, handler: App\Api\UserDeleteHandler, authorization: admin, rateLimit: strict}
```

`authorization` a `rateLimit` endpointu prijímajú:

| Hodnota | Význam |
| --- | --- |
| názov z `api.authorizations` / `api.rateLimits` | ten objekt, spoločný pre všetky endpointy s týmto názvom |
| len `rateLimit`: zoznam názvov alebo `{limit, interval, ...}` | politiky sa kontrolujú postupne, kým niektorá požiadavku neodmietne; vlastný limit tohto endpointu, pozrite si [Obmedzenie požiadaviek](/sk/guide/rate-limit) |
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
		normal: {algorithm: slidingWindow, limit: 100, interval: 1 minute}
		strict: {algorithm: fixedWindow, limit: 5, interval: 1 minute}
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
Class 'App\Api\HelloHandler' in api.authorizations.token must implement Tomaj\NetteApi\Authorization\ApiAuthorizationInterface, but it does not. Implement the interface in the class, or use another class.
```

Preklep v názve zastaví kompiláciu a vypíše názvy, ktoré ste definovali:

```
Unknown authorization 'tokne' in the authorization of endpoint GET /v1/users (api.endpoints[0]), it is not a name from api.authorizations nor an existing class. Did you mean 'token'? Names defined in api.authorizations: 'token'. Or use @serviceName, a class implementing Tomaj\NetteApi\Authorization\ApiAuthorizationInterface, or a statement like App\MyClass(arguments). To add the name, write api: {authorizations: {tokne: App\Api\MyAuthorization}}.
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
| `mode` | `setCorsHeader()`: `'*'`, `'off'`, `'reflect-unsafe'` ([nebezpečné](/sk/guide/cors#mozem-si-vo-4-0-nechat-spravanie-auto), pôvodné `'auto'`) alebo jeden origin (`'auto'` zastaví kompiláciu s ukážkou zoznamu originov na náhradu) | `'off'` |
| `allowedOrigins` | `setCorsAllowedOrigins()` | nenastavené |
| `originValidator` | `setCorsOriginValidator()`: trieda, `@service` alebo statement, ktorý implementuje `CorsOriginValidatorInterface` ([dynamické originy](/sk/guide/cors#dynamicke-originy)), nedá sa kombinovať s `mode` ani `allowedOrigins` | nenastavené |
| `allowCredentials` | druhý argument `setCorsAllowedOrigins()` / `setCorsOriginValidator()`, potrebuje `allowedOrigins` alebo `originValidator` | `false` |
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
		maskHeaders:                 # true (predvolené) | false | zoznam | mapa
			add: [X-Session]
			remove: [Set-Cookie]
			patterns: {add: [x-signature-*], remove: ['*key*']}
		maskQueryParams:             # true (predvolené) | false | zoznam | mapa
			add: [session_id]
		masking: partial             # full (predvolené) | partial | hash | omit | vlastný masker
```

Tajné hlavičky a query parametre sa maskujú predvolene, aj bez sekcie `logging:`. Každý kľúč je nepovinný:

| Kľúč | Hodnoty |
| --- | --- |
| `maskHeaders` | `true` (predvolené), `false` (vypnuté, aj so vzormi a menami z autorizácií), zoznam, ktorý nahradí predvolené mená, alebo `{names, add, remove, patterns}`; `patterns` je `true`, `false`, zoznam alebo `{add, remove}` |
| `maskQueryParams` | `true` (predvolené), `false`, zoznam, ktorý nahradí predvolené mená, alebo `{names, add, remove}` |
| `masking` | `full` (predvolené), `partial`, `hash`, `omit` alebo váš `LogValueMaskerInterface` ako `Class`, `Class(args)` či `@service` |
| `hashKey` | HMAC kľúč pre `masking: hash`, napríklad `%env.API_LOG_HASH_KEY%` |

Mapa použije `names`, potom `add`, potom `remove`. Maskujú sa aj mená, ktoré používajú vaše `HeaderApiKeyAuthentication`, `QueryApiKeyAuthentication` a `CookieApiKeyAuthentication`. To isté nemeňte zároveň v `setup:` vašej služby `ApiPresenter`; rozšírenie by ho nahradilo, takže kompilácia zlyhá. Všetky tvary, predvolené hodnoty, režimy a zalogovaný výstup nájdete v [Logovaní](/sk/guide/logging#referencia-neon).

## IP detektor

```yaml
api:
	ipDetector: App\Api\ProxyIpDetector
```

Predvolený `IpDetector` berie adresu z `Nette\Http\IRequest::getRemoteAddress()`, ktorá verí preposlaným hlavičkám len od proxy v `http: proxy:`. Pozrite si [Ktorá IP sa počíta?](/sk/guide/rate-limit#ktora-ip-sa-pocita).

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
		mode: null             # '*' | 'off' | 'reflect-unsafe' (nebezpečné) | origin, predvolene 'off'
		allowedOrigins: null   # zoznam originov
		originValidator: null  # Trieda | Trieda(args) | @sluzba s CorsOriginValidatorInterface
		allowCredentials: false
		preflight: false       # true | mapa hlavičiek
	logging:
		maskHeaders: true      # false | zoznam hlavičiek | {names, add, remove, patterns}
		maskQueryParams: true  # false | zoznam parametrov | {names, add, remove}
		masking: full          # partial | hash | omit | Class | Class(args) | @service
		hashKey: null          # reťazec, len s masking: hash
	authorizations: []         # nazov: Trieda | Trieda(args) | @sluzba
	rateLimits: []             # nazov: {algorithm, limit, interval, key, name} | Trieda | Trieda(args) | @sluzba
	rateLimiting:
		storage: cache         # memory | redis | Trieda | Trieda(args) | @sluzba
		redis: null            # @sluzba s Redis (ext-redis) alebo Predis\Client, potrebné pre storage: redis
		prefix: 'nette-api:rate-limit:'
		clock: null            # Trieda | Trieda(args) | @sluzba implementujúca Psr\Clock\ClockInterface
		logger: null           # Trieda | Trieda(args) | @sluzba implementujúca Tracy\ILogger, predvolene Tracy log
	defaults:
		authorization: null    # názov | Trieda | Trieda(args) | @sluzba
		rateLimit: null        # názov | zoznam názvov | {limit, interval, ...} | Trieda | Trieda(args) | @sluzba
	endpoints: []              # {method, version, package, action, handler, authorization, rateLimit}
```

Neznámy kľúč je chyba, takže preklep ako `cors: {origins: ...}` nemôže potichu nič nerobiť.

## Chyby v konfigurácii

Každá chyba v sekcii `api:` zastaví kompiláciu kontajnera výnimkou `Nette\DI\InvalidConfigurationException`. Správa (v angličtine) uvádza kľúč v neone, čo je zle a ako to opraviť, väčšinou s príkladom. Najčastejšie:

| Chyba | Príklad | Správa hovorí |
|---|---|---|
| Neznámy kľúč | `cors: {origins: [...]}`, `pakage: users` | `Unexpected item 'api › cors › origins'`, „did you mean“ a všetky povolené kľúče danej sekcie |
| Zlý typ | `endpoints: 5`, `maskHeaders: 5` | čo kľúč očakáva a príklad, napr. `Use a list of endpoints, for example endpoints: [{...}]` |
| Chýbajúci kľúč endpointu | `- {method: GET, version: 1}` | `The mandatory item ... is missing. Every endpoint needs method, version, package and handler` |
| Neznáma hodnota | `errors: {format: problemdetails}` | `Did you mean 'problemDetails'?` a povolené hodnoty |
| Trieda neexistuje | `logger: App\Nope`, `logger: App\Nope(1)` | triedu, kľúč a že treba skontrolovať názov a autoload v composer.json |
| Trieda zlého typu | `logger: stdClass` | aké rozhranie musí trieda implementovať |
| Služba neexistuje alebo je zlého typu | `logger: @nope` | že treba službu zaregistrovať alebo použiť názov triedy |
| Referencia v úvodzovkách | `handler: '@users'` | že treba odstrániť úvodzovky |
| Neznáme meno autorizácie alebo obmedzenia | `authorization: berer` | `Did you mean 'bearer'?` a definované mená |
| Neplatné meno autorizácie alebo obmedzenia | `authorizations: {my-auth: ...}` | že meno má len písmená, číslice a podčiarkovníky |
| Dve obmedzenia s rovnakým `name` | `a: {limit: 5, interval: 60, name: x}`, `b: {limit: 9, interval: 60, name: x}` | že by zdieľali počítadlá, a aby ste každému dali vlastné meno |
| Vlastný limit s `name` pomenovanej politiky | `rateLimits: {perIp: {limit: 5, interval: 60}}`, `rateLimit: {limit: 1, interval: 60, name: perIp}` | pomenovanú politiku, a aby ste vlastnému limitu dali iné meno alebo použili `rateLimit: perIp` |
| Zlý Redis klient | `rateLimiting: {storage: redis, redis: redis}` | že to nie je `@služba` ani Redis klient, a ako to napísať |
| Preklep v HTTP metóde | `method: GETT` | `Did you mean 'GET'?` a HTTP metódy |
| Neplatná verzia, balík alebo akcia | `version: v1`, `package: users/list`, `package: 'u x'` | aké znaky sú povolené, s príkladom |
| Duplicitný endpoint | dvakrát `GET /v1/users` | obe miesta |
| `errors.handler` spolu s `format` alebo `typeBaseUri` | `errors: {handler: ..., format: problemDetails}` | že treba nechať len jedno |
| `typeBaseUri` bez formátu problem details alebo nie absolútne URI | `typeBaseUri: example.com/errors` | že treba pridať `format: problemDetails` alebo použiť napr. `https://example.com/errors/` |
| Neplatné nastavenie CORS | `mode: ''`, `mode: https://a.com/path`, `allowedOrigins: []`, `allowCredentials` bez `allowedOrigins` či `originValidator`, `originValidator` spolu s `mode` alebo `allowedOrigins`, validátor bez `CorsOriginValidatorInterface` | platné režimy alebo originy, čo odstrániť alebo ktoré rozhranie implementovať, s príkladom |
| Prázdny alebo neplatný zoznam hlavičiek | `preflight: {}`, `maskHeaders: []`, `maskHeaders: ['X Secret']` | že pre predvolené treba `true`, na vypnutie maskovania `false`, alebo písať len mená hlavičiek |
| Položka maskovania, ktorá v zozname nie je alebo je chybná | `maskHeaders: {remove: [Set-Cokie]}`, `maskHeaders: {remove: [X-Auth-Token]}`, `maskQueryParams: {add: ['token[]']}` | `Did you mean 'Set-Cookie'?` a aktuálny zoznam, vzor, ktorý hlavičku maskuje, alebo písať len meno |
| Rovnaká položka v `add` aj `remove`, alebo prázdna mapa | `maskHeaders: {add: [X-A], remove: [X-A]}`, `maskHeaders: {}`, `patterns: {}` | nechať ju len v jednom, alebo použiť `true` či `false` |
| Neplatný vzor hlavičky | `patterns: [token]`, `patterns: {add: ['*']}`, `patterns: ['x auth*']` | použiť `*` alebo ho pridať ako meno, že by zodpovedal každej hlavičke, alebo ktoré znaky sú povolené |
| Neznámy režim maskovania alebo zlý `hashKey` | `masking: hashed`, `hashKey` s `masking: full`, `hashKey: ''` | `Did you mean 'hash'?` a režimy, použiť `masking: hash`, alebo dlhý náhodný kľúč |
| Kľúč `logging:` a setup `ApiPresenter` menia to isté | `maskHeaders: false` a `- addMaskedLogHeader(X-A)` v `services:` | že rozšírenie by setup nahradilo a ako volanie presunúť do kľúča |
| Cesta discovery neexistuje alebo je súbor | `paths: [%appDir%/Api/UsersHandler.php]` | že treba adresár |
| `discovery.namespace` bez `paths` alebo bez nájdenej triedy | `discovery: {namespace: App}` | že treba pridať cesty, alebo nájdené namespacy |
| Voľba a vlastná služba rovnakého typu | `logger: ...` a logger v `services:` | že treba jedno z nich odstrániť |

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
api.errors is set, but service 'errorHandler' of type Tomaj\NetteApi\Error\ErrorHandlerInterface is registered in the services section too, and the api extension does not know which one to use. Remove api.errors, or remove the service 'errorHandler'.
```

## Rozhodnutia v návrhu

Zámerné obmedzenia rozšírenia. Každé má issue v backlogu s výhodami, nevýhodami a tým, kedy by sa doplnilo.

### Žiadna voľba `openapi:`

OpenAPI endpoint sa registruje ako každý iný, v `api: endpoints:` s argumentmi handlera.

- **Prečo:** handler má viac nastavení (`initData` s info, servermi a zabezpečením, `allowedEndpoints`, `specBuilder`). Ich kópia v rozšírení by bola druhým spôsobom na to isté.
- **Prečo nie:** viac schémy a dokumentácie na udržiavanie a menej jasné, odkiaľ endpoint pochádza.
- **Kedy by to dávalo zmysel:** väčšina projektov kopíruje ten istý blok, alebo rozšírenie vie doplniť užitočné predvolené hodnoty, napríklad schémy zabezpečenia z `api: authorizations:`. Pozri [#230](https://github.com/tomaj/nette-api/issues/230).

Rozhodnutia o [endpointoch z atribútov](/sk/guide/attributes#rozhodnutia-v-navrhu) sú na ich stránke.

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

## Dajú sa endpointy zapísať priamo na triedy handlerov?

Áno, atribútmi `#[Get]`, `#[Post]` a ďalšími. Rozšírenie ich prečíta pri kompilácii a skontroluje spolu s `endpoints`. Pozrite [Endpointy z atribútov](/sk/guide/attributes).
