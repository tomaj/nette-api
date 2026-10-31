---
title: Logovanie
description: Logovanie požiadaviek Nette API cez ApiLoggerInterface - čo dostane log(), ktoré odpovede sa v 3.x logujú a ako sa maskujú tajné hlavičky a query parametre.
---

# Logovanie

Keď má DI kontajner službu implementujúcu `Tomaj\NetteApi\Logger\ApiLoggerInterface`, `ApiPresenter` ju zavolá po behu handlera.

<Diagram name="logging-1" alt="Odpoveď handlera, alebo odpoveď error handlera po výnimke, sa odovzdá do ApiLoggerInterface::log" />

::: tip Od verzie 4.0.0 sa logy predvolene maskujú
Presenter nahradí hodnoty tajných hlavičiek a tajných query parametrov reťazcom `***` skôr, než sa dostanú do `log()`. Pozrite [Maskovanie](#maskovanie). V 3.x dostal logger hlavičky presne tak, ako prišli, ak ste nezavolali `setMaskedLogHeaders()`.
:::

## Rozhranie

```php
public function log(
    int $responseCode,
    string $requestMethod,
    string $requestHeader,
    string $requestUri,
    string $requestIp,
    string $requestAgent,
    int $responseTime
): bool;
```

| Argument | Hodnota |
| --- | --- |
| `$responseCode` | odoslaný stavový kód |
| `$requestHeader` | všetky hlavičky požiadavky, jedna `Name: value` na riadok, [zamaskované](#maskovanie) |
| `$requestUri` | `REQUEST_URI` aj s query stringom, [zamaskované](#maskovanie) |
| `$requestIp` | zo služby `IpDetectorInterface`, ktorá musí existovať |
| `$responseTime` | milisekundy |

## Príklad

[Príklad logovania](/sk/examples/logging-errors) zapisuje JSON riadky:

<<< @/../examples/logging-errors/app/FileApiLogger.php

```yaml
services:
	- Tomaj\NetteApi\Misc\IpDetector
	- App\FileApiLogger(%appDir%/../log/api.log)
```

## Maskovanie

::: tip Od verzie 4.0.0
Maskovanie je predvolene zapnuté. Vo verzii 3.6.0 bolo len `setMaskedLogHeaders()`, vypnuté, kým ste ho nezavolali. Pozrite [UPGRADE-4.0.md](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md#logs-are-masked-by-default).
:::

Pred zavolaním `log()` presenter nahradí hodnotu každej tajnej hlavičky a každého tajného query parametra. Názov ostane, takže stále vidíte, čo klient poslal. Táto požiadavka:

```text
GET /api/v1/users?api_key=sk_live_4f9a8b7c6d5e&page=2
Authorization: Bearer eyJhbGciOiJIUzI1NiJ9
X-Api-Key: abc123
Cookie: lang=en; PHPSESSID=9f8e7d6c5b4a
X-Request-Id: abc-123
```

príde do `log()` ako:

```text
$requestUri    = /api/v1/users?api_key=***&page=2
$requestHeader = Authorization: ***
                 X-Api-Key: ***
                 Cookie: ***
                 X-Request-Id: abc-123
```

Maskovanie nepotrebuje žiadnu konfiguráciu. Meníte ho na jednom z dvoch miest:

- v `setup:` služby `apiPresenter`, [metódami presentera](#metody-presentera), alebo
- v sekcii `logging:` [DI rozšírenia](#referencia-neon).

### Čo sa predvolene maskuje

| Čo | Predvolene | Konštanta |
| --- | --- | --- |
| mená hlavičiek | `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` | `ApiPresenter::DEFAULT_MASKED_LOG_HEADERS` |
| vzory hlavičiek | `*token*`, `*secret*`, `*key*`, `x-auth-*` | `ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS` |
| query parametre | `api_key`, `apikey`, `key`, `token`, `access_token`, `refresh_token`, `id_token`, `auth`, `password`, `secret`, `signature`, `sig`, `code`, `client_secret` | `ApiPresenter::DEFAULT_MASKED_LOG_QUERY_PARAMS` |
| mená z autorizácií | hlavička každej `HeaderApiKeyAuthentication`, parameter každej `QueryApiKeyAuthentication`, cookie každej `CookieApiKeyAuthentication` | načítané zo zaregistrovaných endpointov pri každej požiadavke |
| režim | `***` | `LogMaskMode::Full` |

### Ako sa položky porovnávajú

- **Mená hlavičiek** sa porovnávajú bez ohľadu na veľkosť písmen: `authorization`, `Authorization` a `AUTHORIZATION` sú jedna položka.
- **Vzor hlavičky** je meno hlavičky, v ktorom `*` znamená ľubovoľné znaky, aj žiadne. Musí zodpovedať celému menu, bez ohľadu na veľkosť písmen. `*token*` zodpovedá `X-Auth-Token`, `X-Refresh-Token` aj `Token`. `x-auth-*` zodpovedá `X-Auth-User`, ale nie `X-Auth` (`-` je povinná). Iný zástupný znak nie je: `?` a `[...]` v mene hlavičky byť nemôžu, takže sa odmietnu.
- **Query parametre** sa porovnávajú bez ohľadu na veľkosť písmen po URL-dekódovaní mena. Každé meno zamaskuje aj svoje poľové tvary: `token` zamaskuje `token=`, `TOKEN=`, `token[]=` aj `token[a]=`. Parameter bez `=` (`?token`) nemá hodnotu a ostane. Prázdna hodnota (`?token=`) sa zamaskuje ako každá iná.
- **Mená z autorizácií** sa pridajú k zoznamom, keď sa požiadavka loguje. Mená hlavičiek a query parametrov sa porovnávajú bez ohľadu na veľkosť písmen, mená cookies presne, tak ako ich číta PHP. Po jednom ich odstrániť nejde, lebo v zoznamoch nie sú a `getMasked*()` ich nevracia. Zmiznú, len keď daný druh maskovania vypnete. Ak niektoré chcete logovať, vypnite daný druh maskovania a ostatné maskujte vo vlastnom loggeri, alebo logujte jeho hash cez `masking: hash`.
- **`Cookie`** je medzi predvolenými menami, takže celá hlavička bude `***`. Keď `Cookie` odstránite, hlavička sa zaloguje a zamaskujú sa v nej len cookies, ktoré číta `CookieApiKeyAuthentication`: `Cookie: lang=en; api_session=***`.
- **Vyhráva posledný zápis**: `addMaskedLogHeader('COOKIE')` ponechá jednu položku a `getMaskedLogHeaders()` vráti `COOKIE`.
- **Poradie zoznamov:** `getMasked*()` vráti položky v poradí pridania, predvolené ako prvé.

### Rýchle recepty

Každý recept ukazuje najprv `setup:` presentera, potom to isté s DI rozšírením. Pre každý zoznam použite len jedno z nich; pozrite [Setup presentera a neon spolu](#setup-presentera-a-neon-spolu).

**Zamaskovať ďalšiu hlavičku, query parameter alebo vzor hlavičky:**

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- addMaskedLogHeader(X-Session)
			- addMaskedLogHeaderPattern(x-signature-*)
			- addMaskedLogQueryParam(session_id)
```

```neon
api:
	logging:
		maskHeaders: {add: [X-Session], patterns: {add: [x-signature-*]}}
		maskQueryParams: {add: [session_id]}
```

**Logovať jednu predvolenú položku celú** (tu `Set-Cookie`, vzor `*key*` a `code`):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- removeMaskedLogHeader(Set-Cookie)
			- removeMaskedLogHeaderPattern('*key*')
			- removeMaskedLogQueryParam(code)
```

```neon
api:
	logging:
		maskHeaders: {remove: [Set-Cookie], patterns: {remove: ['*key*']}}
		maskQueryParams: {remove: [code]}
```

**Nahradiť celý zoznam** (vzory hlavičiek a mená z autorizácií ostanú):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders([Authorization, X-Session])
			- setMaskedLogHeaderPatterns(['x-secret-*'])
			- setMaskedLogQueryParams([api_key, token])
```

```neon
api:
	logging:
		maskHeaders: {names: [Authorization, X-Session], patterns: ['x-secret-*']}
		maskQueryParams: [api_key, token]
```

**Vypnúť maskovanie**, aby sa všetko logovalo ako v 3.x:

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders([])
			- setMaskedLogQueryParams([])
```

```neon
api:
	logging:
		maskHeaders: false
		maskQueryParams: false
```

`setMaskedLogHeaders([])` vypne mená, vzory aj hlavičky a cookies z autorizácií. `setMaskedLogQueryParams([])` vypne parametre aj parametre z autorizácií. Vypnúť môžete aj len jedno z nich.

**Zmeniť režim**, pozrite [Režimy maskovania](#rezimy-maskovania):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setLogMasking(Tomaj\NetteApi\Logger\LogMaskMode::Partial)
```

```neon
api:
	logging:
		masking: partial
```

**Použiť vlastný masker**, pozrite [Vlastný masker](#vlastny-masker):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setLogMasking(App\Api\LengthMasker())
```

```neon
api:
	logging:
		masking: App\Api\LengthMasker
```

`apiPresenter` vyššie je jediná služba zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby): pridajte k nej riadky `setup:`, alebo dajte blok pod rovnakým názvom do iného konfiguračného súboru a Nette ich zlúči. Druhú službu `ApiPresenter` neregistrujte; dve skončia chybou „Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found“. Vzor, ktorý začína `*`, dajte v NEON do úvodzoviek: `'*key*'`.

### Metódy presentera

| Metóda | Účinok |
| --- | --- |
| `setMaskedLogHeaders(array $headers = DEFAULT_MASKED_LOG_HEADERS)` | nahradí mená hlavičiek. Bez argumentu obnoví predvolené. Vzory ostanú. |
| `setMaskedLogHeaders([])` | vypne maskovanie hlavičiek: vymaže mená aj vzory a vynechá hlavičky a cookies z autorizácií |
| `addMaskedLogHeader(string $header)` | pridá jedno meno. Po `setMaskedLogHeaders([])` maskovanie hlavičiek znova zapne, s menami z autorizácií, ale bez vymazaných vzorov. |
| `removeMaskedLogHeader(string $header)` | odstráni jedno meno. Zlyhá, keď meno v zozname nie je. Po odstránení posledného mena ostanú vzory aj mená z autorizácií. |
| `setMaskedLogHeaderPatterns(array $patterns = DEFAULT_MASKED_LOG_HEADER_PATTERNS)` | nahradí vzory. `[]` vymaže len vzory; mená ostanú. Neprázdny zoznam maskovanie hlavičiek znova zapne. |
| `addMaskedLogHeaderPattern(string $pattern)` | pridá jeden vzor a maskovanie hlavičiek znova zapne |
| `removeMaskedLogHeaderPattern(string $pattern)` | odstráni jeden vzor. Zlyhá, keď vzor v zozname nie je. |
| `setMaskedLogQueryParams(array $params = DEFAULT_MASKED_LOG_QUERY_PARAMS)` | nahradí parametre. `[]` vypne maskovanie query aj s parametrami z autorizácií. |
| `addMaskedLogQueryParam(string $param)` | pridá jeden parameter a maskovanie query znova zapne |
| `removeMaskedLogQueryParam(string $param)` | odstráni jeden parameter. Zlyhá, keď parameter v zozname nie je. |
| `getMaskedLogHeaders()`, `getMaskedLogHeaderPatterns()`, `getMaskedLogQueryParams()` | `list<string>`: aktuálne zoznamy, bez mien z autorizácií |
| `setLogMasking(LogMaskMode\|LogValueMaskerInterface $masking)` | ako sa hodnota zamaskuje. `LogMaskMode` sa zmení na `new LogValueMasker($mode)`. Pre kľúč hashu odovzdajte `new LogValueMasker(LogMaskMode::Hash, $key)`. |
| `getLogMasking()` | aktuálny `LogValueMaskerInterface`, predvolene `LogValueMasker` s `LogMaskMode::Full` |

Volania bežia v poradí `setup:`. Každé mení to, čo nechali predchádzajúce, takže `setMaskedLogHeaders([X-A])` a potom `addMaskedLogHeader(X-B)` zamaskuje obe, opačné poradie len `X-A`.

Každá položka sa skontroluje pri volaní. Chyba zlyhá s tým, čo je zle a ako to opraviť:

```text
removeMaskedLogHeader('Set-Cokie'): 'Set-Cokie' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. Did you mean 'Set-Cookie'? Entries compare case-insensitively; fix the spelling or leave the entry out.
removeMaskedLogHeader('X-Auth-Token'): 'X-Auth-Token' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. It is masked by the pattern '*token*'; remove the pattern, or replace the patterns with a narrower list.
addMaskedLogHeader('X-*'): it contains *, which is only for header patterns. Add it as a pattern, or write the full header name.
setMaskedLogHeaders(): 'X Secret' in the list: it is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example X-Session.
addMaskedLogHeaderPattern('token'): it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*.
addMaskedLogHeaderPattern('*'): it matches every header, so nothing useful would be logged. Name a part of the header, for example *token*, or leave the headers out in your logger.
addMaskedLogQueryParam('token[]'): it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x]).
removeMaskedLogQueryParam('cod'): 'cod' is not in the masked query parameters (api_key, apikey, key, token, access_token, refresh_token, id_token, auth, password, secret, signature, sig, code, client_secret), so there is nothing to remove. Did you mean 'code'? Entries compare case-insensitively; fix the spelling or leave the entry out.
new LogValueMasker(LogMaskMode::Partial, hashKey): the hash key is used only by LogMaskMode::Hash. Use new LogValueMasker(LogMaskMode::Hash, $key), or leave the key out.
```

Chybné volanie v `setup:` zlyhá pri prvej požiadavke, keď sa presenter vytvára. S DI rozšírením zlyhajú rovnaké chyby pri kompilácii kontajnera.

### Referencia neon

S [DI rozšírením](/sk/guide/di-extension#logovanie) sa maskovanie nastavuje v `api: logging:`. Bez tejto sekcie platia predvolené hodnoty presentera.

```neon
api:
	logging:
		maskHeaders: true          # true | false | zoznam mien | mapa, pozrite nižšie
		maskQueryParams: true      # true | false | zoznam mien | mapa, pozrite nižšie
		masking: full              # full | partial | hash | omit | Class | Class(args) | @service
		hashKey: null              # reťazec, len s masking: hash
```

| Kľúč | Typ | Predvolené | Účinok |
| --- | --- | --- | --- |
| `maskHeaders` | `true` | áno | predvolené mená, vzory a mená z autorizácií |
| | `false` | | maskovanie hlavičiek vypnuté: žiadne mená, vzory ani hlavičky a cookies z autorizácií (`setMaskedLogHeaders([])`) |
| | zoznam, `[Authorization, X-Session]` | | nahradí predvolené mená; predvolené vzory a mená z autorizácií ostanú (`setMaskedLogHeaders([...])`) |
| | mapa `{names, add, remove, patterns}` | | mení predvolené hodnoty po jednej, pozrite nižšie |
| `maskHeaders.names` | zoznam | predvolené mená | nahradí predvolené mená pred `add` a `remove` |
| `maskHeaders.add` | zoznam | `[]` | pridá mená |
| `maskHeaders.remove` | zoznam | `[]` | odstráni mená, ktoré sú v predvolených alebo v `names` |
| `maskHeaders.patterns` | `true` | áno | predvolené vzory |
| | `false` | | žiadne vzory; mená ostanú |
| | zoznam, `['*token*', 'x-auth-*']` | | nahradí predvolené vzory |
| | mapa `{add, remove}` | | pridá alebo odstráni vzory |
| `maskQueryParams` | `true` | áno | predvolené parametre a parametre z autorizácií |
| | `false` | | maskovanie query vypnuté, aj pre parametre z autorizácií |
| | zoznam, `[api_key, token]` | | nahradí predvolené parametre; parametre z autorizácií ostanú |
| | mapa `{names, add, remove}` | | to isté ako pri `maskHeaders`, bez `patterns` |
| `masking` | `full`, `partial`, `hash`, `omit` | `full` | [režim](#rezimy-maskovania) |
| | `App\Api\MyMasker`, `App\Api\MyMasker(args)`, `@myMasker` | | váš [masker](#vlastny-masker); trieda musí implementovať `LogValueMaskerInterface` |
| `hashKey` | reťazec, `%env.API_LOG_HASH_KEY%` | `null` | HMAC kľúč pre `masking: hash`. Bez neho sa použije obyčajný SHA-256. |

Mapa použije najprv `names`, potom `add`, potom `remove` a zmení ich na rovnaké volania presentera. Každý kľúč mapy je nepovinný, ale mapa musí niečo meniť:

```neon
api:
	logging:
		maskHeaders:
			names: [Authorization, Cookie, X-Session]   # namiesto 5 predvolených
			add: [X-Device-Id]                          # potom pridať
			remove: [Cookie]                            # potom odstrániť, len z names alebo predvolených
			patterns:
				add: [x-signature-*]
				remove: ['*key*']
		maskQueryParams:
			add: [session_id]
			remove: [code]
		masking: hash
		hashKey: %env.API_LOG_HASH_KEY%
```

Toto zamaskuje `Authorization`, `X-Session` a `X-Device-Id`, hlavičky, ktoré zodpovedajú `*token*`, `*secret*`, `x-auth-*` alebo `x-signature-*`, a mená z autorizácií. `X-Api-Key` sa zaloguje, lebo nie je v `names` a `*key*` bol odstránený. `Cookie` sa zaloguje celá, okrem cookies `CookieApiKeyAuthentication`. Query parametre sú 13 predvolených bez `code`, plus `session_id`. Hodnoty budú `hash:` a 16 hex znakov HMAC.

`true` a `masking: full` nepridajú žiadne volanie, takže presenter ostane pri predvolených. Ostatné hodnoty pridajú pri kompilácii kontajnera volania `setMaskedLog*` / `add*` / `remove*` / `setLogMasking`.

### Setup presentera a neon spolu

Rozšírenie pridá svoje volania za `setup:` služby `apiPresenter`, takže by nahradili to, čo zmenil setup. Keď sa kľúč `logging:`, ktorý nie je na predvolenej hodnote, stretne s volaním v setupe pre to isté, kompilácia zlyhá:

```text
api.logging.maskHeaders and the setup of the Tomaj\NetteApi\Presenters\ApiPresenter service call addMaskedLogHeader() both change the masked headers. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.maskHeaders (for example addMaskedLogHeader(X-Session) is maskHeaders: {add: [X-Session]}), or leave api.logging.maskHeaders out.
```

| Kľúč `logging:` | konfliktné volania v `setup:` presentera |
| --- | --- |
| `maskHeaders` | `setMaskedLogHeaders`, `addMaskedLogHeader`, `removeMaskedLogHeader`, `setMaskedLogHeaderPatterns`, `addMaskedLogHeaderPattern`, `removeMaskedLogHeaderPattern` |
| `maskQueryParams` | `setMaskedLogQueryParams`, `addMaskedLogQueryParam`, `removeMaskedLogQueryParam` |
| `masking`, `hashKey` | `setLogMasking` |

Kľúče sú samostatné. Môžete napríklad nastaviť hlavičky v `setup:` a `masking: hash` v neon. Kľúč na predvolenej hodnote (`maskHeaders: true`, `masking: full`) nikdy nie je v konflikte.

Chyby v neon zlyhajú pri kompilácii kontajnera a uvedú kľúč:

```text
api.logging.maskHeaders.remove[0]: 'Set-Cokie' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. Did you mean 'Set-Cookie'? Entries compare case-insensitively; fix the spelling or leave the entry out.
api.logging.maskHeaders.remove[0] 'X-A' is also in api.logging.maskHeaders.add, so it would be added and removed again. Keep it in one of them.
api.logging.maskHeaders is an empty map, so it changes nothing. Use true for the defaults, false to turn the masking off, or names, add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.
api.logging.maskHeaders is an empty list. To turn the masking off write false; to change the defaults use add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.
api.logging.maskHeaders.patterns[0] 'token': it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*.
api.logging.maskQueryParams.add[0] 'x[]': it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x]).
api.logging.masking 'parital' is not a masking mode. Did you mean 'partial'? Use 'full', 'partial', 'hash', 'omit', or a class implementing Tomaj\NetteApi\Logger\LogValueMaskerInterface, @serviceName or a statement like App\Api\MyMasker(%tempDir%) for your own.
api.logging.hashKey is set, but api.logging.masking is 'full' and only hash uses the key. Use masking: hash with the key, or remove hashKey.
api.logging.hashKey is empty, so it would not protect short secrets. Use a long random secret, for example hashKey: %env.API_LOG_HASH_KEY%, or remove hashKey for a plain SHA-256.
Unexpected item 'api › logging › maskHeaders › ad', did you mean 'add'? Allowed keys in api.logging.maskHeaders: names, add, remove, patterns.
```

Celý zoznam je v tabuľke [Chyby v konfigurácii](/sk/guide/di-extension#chyby-v-konfiguracii).

### Režimy maskovania

Režim určuje, čím sa zamaskovaná hodnota nahradí. Je rovnaký pre hlavičky, query parametre aj cookies. Požiadavka zo začiatku stránky sa zaloguje ako:

| Režim | `$requestUri` | `$requestHeader` |
| --- | --- | --- |
| `full` (predvolený) | `/api/v1/users?api_key=***&page=2` | `Authorization: ***`<br>`X-Api-Key: ***`<br>`Cookie: ***`<br>`X-Request-Id: abc-123` |
| `partial` | `/api/v1/users?api_key=sk_l***&page=2` | `Authorization: Bearer eyJh***`<br>`X-Api-Key: ***`<br>`Cookie: lang***`<br>`X-Request-Id: abc-123` |
| `hash` | `/api/v1/users?api_key=hash:2592602aa93aef79&page=2` | `Authorization: hash:5d78b6eb771afd1e`<br>`X-Api-Key: hash:6ca13d52ca70c883`<br>`Cookie: hash:83e1eafde364581e`<br>`X-Request-Id: abc-123` |
| `hash` s `hashKey: my-long-random-key` | `/api/v1/users?api_key=hash:97c5041103206d22&page=2` | `Authorization: hash:7fc0ed98d7c6352e`<br>`X-Api-Key: hash:e4984104dab10337`<br>`Cookie: hash:6f6bf54c6f1caeb4`<br>`X-Request-Id: abc-123` |
| `omit` | `/api/v1/users?page=2` | `X-Request-Id: abc-123` |

| Režim | Pravidlo | Použitie |
| --- | --- | --- |
| `LogMaskMode::Full` | hodnota bude `***` | nič z tajomstva v logu |
| `LogMaskMode::Partial` | prvé 4 znaky ostanú a zvyšok bude `***`. Hodnota kratšia ako 12 znakov bude len `***`. Úvodná autentifikačná schéma (`Basic`, `Bearer`, `Digest`, `DPoP`, `HOBA`, `Mutual`, `Negotiate`, `NTLM`, `Token`, `ApiKey`, `Hawk`, `AWS4-HMAC-SHA256`, `SCRAM-SHA-1`, `SCRAM-SHA-256`, `vapid`, s ľubovoľnou veľkosťou písmen) a medzera za ňou ostanú a pravidlo platí pre zvyšok: `Bearer short` bude `Bearer ***`. | rozlíšiť kľúče, vidieť, ktorý klient poslal ktorý kľúč |
| `LogMaskMode::Hash` | `hash:` a prvých 16 hex znakov SHA-256 celej hodnoty, alebo HMAC-SHA-256 s `hashKey`. Zahašuje sa aj schéma. | nájsť všetky požiadavky s rovnakým kľúčom bez jeho zalogovania |
| `LogMaskMode::Omit` | riadok hlavičky sa vynechá. Dvojica `name=value` sa z query vynechá a bez ďalších parametrov zmizne aj `?` (`/x?token=a` sa zaloguje ako `/x`). Cookie `CookieApiKeyAuthentication` sa z hlavičky `Cookie` vynechá. | nič o tajomstve, ani to, že bolo poslané |

Partial počíta bajty, takže ho nechajte pre ASCII tajomstvá. Pri `Basic` sú 4 ponechané znaky base64 začiatku mena používateľa. Pri celej hlavičke `Cookie` sú to začiatok prvej cookie, `lang***`.

::: warning Hash nie je šifrovanie
Bez `hashKey` je hodnota prvých 16 hex znakov obyčajného SHA-256. Ktokoľvek s logom môže zahašovať všetkých kandidátov a porovnať. 6-miestny PIN, slovo alebo krátky či známy kľúč sa nájde za pár sekúnd. Nastavte `hashKey` na dlhé náhodné tajomstvo, ktoré nie je v logu ani v repozitári, napríklad `%env.API_LOG_HASH_KEY%`. Hodnota je potom HMAC-SHA-256, ktorý sa bez kľúča overiť nedá. Bez DI rozšírenia odovzdajte `new LogValueMasker(LogMaskMode::Hash, $key)`. Keď kľúč zmeníte, rovnaké tajomstvo dá iný hash, takže staršie riadky logu sa už nezhodujú. Kľúč funguje len s `hash`; s iným režimom zlyhá a zlyhá aj prázdny kľúč.
:::

### Vlastný masker

Na čokoľvek iné implementujte `Tomaj\NetteApi\Logger\LogValueMaskerInterface`:

```php
namespace App\Api;

use Tomaj\NetteApi\Logger\LogValueMaskerInterface;

final class LengthMasker implements LogValueMaskerInterface
{
    public function mask(string $value): ?string
    {
        return '*** (' . strlen($value) . ' chars)';
    }
}
```

- `mask()` dostane pôvodnú hodnotu: celú hodnotu hlavičky, URL-dekódovanú query hodnotu alebo hodnotu jednej cookie.
- Vráti text do logu, alebo `null`, aby sa hlavička, parameter či cookie vynechali, ako pri `omit`.
- Vrátená query hodnota sa znova URL-zakóduje (len `*` a `:` ostanú, ako sú), takže `*** (3 chars)` sa zaloguje ako `?api_key=***%20%283%20chars%29`. Hlavička dostane text tak, ako je: `Authorization: *** (27 chars)`.
- Masker je jeden objekt pre všetky požiadavky. Tajomstvá, napríklad kľúče, mu dajte v konštruktore.

```neon
api:
	logging:
		masking: App\Api\LengthMasker            # alebo App\Api\LengthMasker(%tempDir%), alebo @myMasker
```

Bez rozšírenia `setLogMasking(App\Api\LengthMasker())` v `setup:`.

### Čo sa nemaskuje

- Telo: `log()` nikdy nedostane telo požiadavky ani odpovede. Ak telá logujete inde, maskujte ich tam.
- Argumenty `$requestAgent` a `$requestIp`.
- [Konzola](/sk/guide/console) a Tracy. Konzola ukazuje požiadavku, ktorú zadáte, a lišta a bluescreen Tracy ukazujú surové hlavičky a `$_GET`. Tracy nechajte v produkčnom režime, kde sa bluescreen neukazuje, a ak treba, jej logy maskujte sami.
- Segmenty cesty: `/api/v1/users/secret-id` ostane, ako je. Tajomstvá do cesty nedávajte.
- Hlavičky odpovede: logujú sa len hlavičky požiadavky.
- Vaše vlastné volania `log()` inde, napríklad do Tracy.

### Bezpečnostné poznámky

- Predvolené hodnoty pokrývajú len bežné mená. Overte, čo posielajú vaši klienti, a pridajte vlastné mená, napríklad `X-Session` alebo `session_id`.
- `removeMaskedLog*()`, `false` a užší zoznam logujú skutočné tajomstvá. Radšej odstráňte jednu položku, než by ste maskovanie vypli.
- Mená z autorizácií sa maskujú, aj keď zoznamy nahradíte, takže kľúč, ktorý API číta, sa do logu nedostane. Vypne ich len `false`, `setMaskedLogHeaders([])` alebo `setMaskedLogQueryParams([])`.
- Logy sa často uchovávajú dlhšie a číta ich viac ľudí než databázu. Zamaskovaný log je stále osobný údaj, keď obsahuje IP a user agentov.

## Čo sa loguje

| Odpoveď | Loguje sa v 3.x |
| --- | --- |
| výsledok handlera, ľubovoľný kód | áno |
| `500` z výnimky alebo zlyhanej kontroly výstupu | áno |
| `404 Unknown api endpoint` | áno |
| `400` zlý vstup, `401`, `429`, `500` z výnimky v `authorized()` | nie, presenter skončí pred loggerom |

## Časté otázky

### Prečo v logu chýbajú moje odpovede 401?

V 3.x končia predčasne, pozrite tabuľku. Ak ich potrebujete, logujte ich vo vlastnom [`error handleri`](/sk/guide/errors).

### Potrebujem na logovanie detektor IP?

Áno, presenter berie `IpDetectorInterface` z kontajnera. Vstavaný `IpDetector` sa nedá podvrhnúť hlavičkami; za reverse proxy ju uveďte v `http: proxy:`, pozrite si [Autorizáciu](/sk/guide/authorization#tokeny-a-obmedzenie-ip).

### Kam idú výnimky?

Do Tracy, cez `Debugger::log()` v `DefaultErrorHandler`. Pozrite si [Spracovanie chýb](/sk/guide/errors).

### Môžem logovať telo odpovede?

Cez toto rozhranie nie, dostane len kód. Ak potrebujete viac, obaľte handler alebo `error handler`.

### Záleží na návratovej hodnote log()?

Nie, presenter ju ignoruje.

### Mám maskovanie nechať aj vo vlastnom loggeri?

Pre hlavičky a query parametre nie, tie zamaskuje presenter. Nechajte ho pre všetko ostatné, čo logujete, napríklad segmenty cesty alebo telá.

### Prečo je moja hlavička X-Request-Key zamaskovaná?

Zodpovedá jej vzor `*key*`. Odstráňte ho cez `removeMaskedLogHeaderPattern('*key*')` alebo `maskHeaders: {patterns: {remove: ['*key*']}}`. `X-Api-Key` ostane zamaskovaná, lebo je aj medzi menami.

### Ako zalogujem hlavičku mojej HeaderApiKeyAuthentication?

Meno z autorizácie samostatne odstrániť nejde; vypne ho len `maskHeaders: false` (alebo `setMaskedLogHeaders([])`) spolu s celým maskovaním hlavičiek. Namiesto toho logujte hash, `masking: hash` s `hashKey`, a kľúče rozlíšite.

### Prečo kompilácia zlyhá s „both change the masked headers“?

`setup:` presentera aj sekcia `logging:` menia ten istý zoznam a rozšírenie by setup nahradilo. Nechajte ho na jednom mieste, pozrite [Setup presentera a neon spolu](#setup-presentera-a-neon-spolu).

### Je režim rovnaký pre hlavičky aj query parametre?

Áno, jeden masker spracuje každú zamaskovanú hodnotu. [Vlastný masker](#vlastny-masker) dostane len hodnotu, nie meno hlavičky či parametra, takže ani on ich nerozlíši.
