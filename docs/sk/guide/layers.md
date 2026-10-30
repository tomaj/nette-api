---
title: Vrstvy
description: Čo robí každá vrstva Nette API, od routy a ApiPresentera po autorizáciu, parametre, handler, výstupy, chyby a logger, a ktoré rozhranie ju mení.
---

# Vrstvy

Požiadavka prejde pevnou sadou vrstiev. Každá má jednu úlohu a vlastné rozhranie, takže jednu vymeníte bez zásahu do ostatných. Handler, kde je vaša logika, vidí iba čisté hodnoty.

Jedna požiadavka, ktorá prejde, jedna bez tokenu a jedna so zlým vstupom:

<LayersFlow />

<Diagram name="layers-1" alt="Routa, ApiPresenter, ApiDecider, obmedzenie požiadaviek, autorizácia, parametre, handler, výstupy, error handler a logger, každá so svojím rozhraním" />

| Vrstva | Rozhranie alebo trieda | Úloha | Zastaví požiadavku s |
| --- | --- | --- | --- |
| Routa | Nette router | Zmení `/api/v1/users` na `version`, `package`, `apiAction` | |
| Presenter | `ApiPresenter` | Spustí vrstvy nižšie v poradí | |
| CORS | `setCorsHeader()`, `setCorsAllowedOrigins()` | Pridá hlavičky `Access-Control-*` | |
| Hľadanie endpointu | `ApiDecider`, `EndpointIdentifier` | Nájde handler, autorizáciu a obmedzenie požiadaviek | `404` |
| Obmedzenie požiadaviek | `RateLimitInterface` | Počíta volania klienta | `429` |
| Parametre | `InputParam`, `ParamsProcessor` | Načíta a overí vstup; chyby počkajú na autorizáciu | |
| Autorizácia | `ApiAuthorizationInterface` | Rozhodne, kto smie volať | `401` |
| Chyby parametrov | `ErrorHandlerInterface::handleInputParams()` | Nahlási neplatný vstup | `400` |
| Handler | `ApiHandlerInterface`, `BaseHandler` | Vaša logika, vráti odpoveď | ľubovoľný kód |
| Výstupy | `OutputInterface`, `ConfiguratorInterface` | Overí odpoveď voči schéme | `500` |
| Chyby | `ErrorHandlerInterface` | Zmení zlyhania na JSON | |
| Logger | `ApiLoggerInterface` | Zapíše spracované požiadavky | |

Presné poradie aj s kódom je na stránke [Spracovanie v ApiPresenteri](/sk/guide/presenter).

## Routa

Jedna routa pošle každú URL API do presentera. Zástupné symboly sa stanú časťami endpointu.

```php
$router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
```

`GET /api/v1/users/detail` dá `version` `1`, `package` `users` a `apiAction` `detail`. Pozrite si [Začíname](/sk/guide/getting-started).

## ApiDecider: ktorý endpoint

`ApiDecider` drží zoznam endpointov. Každá položka spája `EndpointIdentifier` s handlerom, autorizáciou a voliteľným obmedzením požiadaviek:

```yaml
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'detail'), App\Api\UserDetailHandler, @bearer, @userLimit)
```

**Kto smie volať** a **ako často** teda patrí k endpointu, nie k handleru. Jeden handler môže byť pod jednou URL verejný a pod inou chránený. Pozrite si [ApiDecider a endpointy](/sk/guide/api-decider).

## Obmedzenie požiadaviek: ako často

`RateLimitInterface::check()` vráti `null` pre žiadny limit, alebo `RateLimitResponse` s limitom a zvyškom. Pri `0` presenter odpovie `429`. Beží pred autorizáciou, takže obmedzený je aj klient, ktorý hádá tokeny. Pozrite si [Obmedzenie počtu požiadaviek](/sk/guide/rate-limit) a [príklad obmedzenia požiadaviek](/sk/examples/rate-limit).

## Autorizácia: kto

`ApiAuthorizationInterface` má dve metódy:

```php
public function authorized(): bool;
public function getErrorMessage(): ?string;
```

`false` odpovie `401` so správou. Súčasťou knižnice sú `NoAuthorization`, `BearerTokenAuthorization`, API kľúče v hlavičke, query a cookie a `BasicAuthentication`. Pre roly alebo session si napíšte vlastnú, ako v [príklade vlastnej autorizácie](/sk/examples/custom-authorization). Pozrite si [Autorizácia](/sk/guide/authorization).

## Parametre: čo prišlo

`params()` handlera vymenuje vstupy. Každý parameter vie, odkiaľ čítať (query, POST, PUT, cookie, súbor, surové alebo JSON telo), či je povinný, jeho typ a povolené hodnoty:

```php
public function params(): array
{
    return [
        (new GetInputParam('page', InputType::INTEGER))->setDefault(1),
        (new JsonInputParam('order', file_get_contents(__DIR__ . '/schema/order.json')))->setRequired(),
    ];
}
```

Presenter ich načíta všetky a chyby nahlási ako `400` so správou pre každý parameter. Handler neplatný vstup nikdy neuvidí. Pozrite si [Vstupné parametre](/sk/guide/params).

## Handler: vaša logika

Handler dostane `$params` s čistými hodnotami a vráti odpoveď. Neoveruje tokeny ani neparsuje požiadavku.

```php
public function handle(array $params): ResponseInterface
{
    $user = $this->users->find($params['id']);
    if ($user === null) {
        return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
    }
    return new JsonApiResponse(200, ['id' => $user->id, 'name' => $user->name]);
}
```

`summary()`, `description()`, `tags()` a `deprecated()` popisujú endpoint pre [OpenAPI](/sk/guide/openapi). Pozrite si [Handlery](/sk/guide/handlers).

## Výstupy: čo odchádza

`outputs()` vymenuje povolené odpovede, každú s kódom a JSON schémou. Keď je validácia zapnutá, odpoveď musí zodpovedať aspoň jednej, inak klient dostane `500`. Handler bez výstupov sa nekontroluje. Kedy kontrolovať, rozhodne konfigurátor, napríklad `EnvConfigurator` iba mimo produkcie. Pozrite si [Výstupy](/sk/guide/outputs).

## Error handler: keď niečo zlyhá

`ErrorHandlerInterface` zostaví odpoveď pre každé zlyhanie:

| Metóda | Kedy | Predvolená odpoveď |
| --- | --- | --- |
| `handle()` | handler vyhodil výnimku | `500`, mimo produkcie s `detail` |
| `handleInputParams()` | zlé parametre | `400 wrong input` |
| `handleSchema()` | výstup nezodpovedá | `500` |
| `handleAuthorization()` | `authorized()` vrátil false | `401` so správou |
| `handleAuthorizationException()` | `authorized()` vyhodil výnimku | `500` so správou výnimky |

Pozrite si [Spracovanie chýb](/sk/guide/errors).

## Logger: čo sa stalo

So službou `ApiLoggerInterface` v kontajneri sa zaloguje každá požiadavka, ktorá sa dostala k handleru, s kódom, metódou, hlavičkami, URI, IP, user agentom a časom. Tajné hlavičky a query parametre sa predvolene maskujú. Pozrite si [Logovanie](/sk/guide/logging).

## Časté otázky

### Kam patrí biznis logika?

Do handlera alebo do služby, ktorú volá. Ostatné vrstvy iba rozhodujú, či sa spustí, a kontrolujú, čo vráti.

### Môže handler prečítať autorizovaného používateľa?

Áno, cez zdieľanú službu, ktorú autorizácia naplní, ako `CurrentUser` v [príklade vlastnej autorizácie](/sk/examples/custom-authorization).

### Prečo sa parametre čítajú pred autorizáciou, ale hlásia po nej?

Aby volajúci bez prístupu dostal `401` a o parametroch sa nič nedozvedel. `authorized()` nemá argumenty a načítané hodnoty nikdy nevidí; autorizácia, ktorá nejakú potrebuje, si ju prečíta z HTTP požiadavky sama, ako `QueryApiKeyAuthentication` číta `$_GET`. Hodnoty dostane len `error handler`, a pri neplatných parametroch sú `[]`.

### Ktoré vrstvy môžem vynechať?

Obmedzenie požiadaviek, logger a výstupy sú voliteľné. Autorizácia je povinná pre každý endpoint; pre verejné použite `NoAuthorization`.

### Ako otestujem jednu vrstvu?

Každá je obyčajný objekt: zavolajte priamo `handle()`, `authorized()`, `ParamsProcessor` alebo `validate()`. Pozrite si [príklad testovania](/sk/examples/testing).
