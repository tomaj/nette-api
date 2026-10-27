---
title: Referencia
description: Všetky verejné triedy a rozhrania Nette API 3.x podľa namespace, s jednoriadkovým popisom a odkazom na stránku príručky.
---

# Referencia

Všetky triedy sú pod `Tomaj\NetteApi`.

## Jadro

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `ApiDecider` | register endpointov: `addApi()`, `getApi()`, `getApis()`, `enableGlobalPreflight()` | [Architektúra](/sk/guide/architecture) |
| `Api` | jeden zaregistrovaný endpoint: endpoint, handler, autorizácia, obmedzenie požiadaviek | [Architektúra](/sk/guide/architecture) |
| `ApiHolder` | používa ho `ApiDecider`: ako uchováva endpoint pred vytvorením handlera; handler môže byť ešte názov triedy alebo služby | [ApiDecider](/sk/guide/api-decider#tri-sposoby-ako-odovzdat-handler) |
| `EndpointIdentifier` / `EndpointInterface` | metóda, verzia, balík, akcia | [Handlery](/sk/guide/handlers#registracia-handlera) |
| `Presenters\ApiPresenter` | spracuje požiadavku krok po kroku | [Spracovanie v ApiPresenteri](/sk/guide/presenter) |

## Handlery

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `Handlers\ApiHandlerInterface`, `BaseHandler` | vaše endpointy | [Handlery](/sk/guide/handlers) |
| `Handlers\OpenApiHandler` | OpenAPI 3.0 dokument | [OpenAPI](/sk/guide/openapi) |
| `Handlers\ApiListingHandler` | JSON zoznam endpointov vo vlastnej verzii | [Handlery](/sk/guide/handlers#handlery-ktore-su-sucastou-kniznice) |
| `Handlers\AlwaysOkHandler`, `EchoHandler` | health check a testovacie endpointy | [Handlery](/sk/guide/handlers#handlery-ktore-su-sucastou-kniznice) |
| `Handlers\DefaultHandler` | `404 Unknown api endpoint` | [Architektúra](/sk/guide/architecture) |
| `Handlers\CorsPreflightHandler(Interface)` | odpovedá na `OPTIONS` | [CORS](/sk/guide/cors#preflight-poziadavky) |

## Parametre a validácia

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `Params\GetInputParam`, `PostInputParam`, `PutInputParam`, `CookieInputParam`, `FileInputParam`, `RawInputParam`, `JsonInputParam` | každá jeden vstup | [Parametre](/sk/guide/params) |
| `Params\InputParam`, `ParamInterface`, `ParamsProcessor` | základná trieda a spracovanie | [Parametre](/sk/guide/params) |
| `Validation\InputValidator`, `JsonSchemaValidator`, `InputType` | kontroly hodnôt | [Parametre](/sk/guide/params#typy) |
| `ValidationResult\ValidationResult(Interface)` | výsledok kontroly | [Parametre](/sk/guide/params) |

## Odpovede a výstupy

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `Response\ResponseInterface` | čo implementuje každá odpoveď | [Výstupy](/sk/guide/outputs#odpovede) |
| `Response\JsonApiResponse`, `TextApiResponse`, `XmlApiResponse`, `RedirectResponse` | čo vracia `handle()` | [Výstupy](/sk/guide/outputs#odpovede) |
| `Output\JsonOutput`, `RedirectOutput`, `AbstractOutput`, `OutputInterface` | deklarované výstupy | [Výstupy](/sk/guide/outputs#vystupy) |
| `Output\Configurator\ConfiguratorInterface`, `EnvConfigurator`, `QueryConfigurator`, `DebuggerConfigurator` | kedy kontrolovať výstupy a zobrazovať detaily | [Výstupy](/sk/guide/outputs#kedy-sa-vystupy-validuju) |
| `Error\ErrorHandlerInterface`, `DefaultErrorHandler` | chybové odpovede | [Spracovanie chýb](/sk/guide/errors) |

## Bezpečnosť

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `Authorization\NoAuthorization`, `BearerTokenAuthorization`, `HeaderApiKeyAuthentication`, `QueryApiKeyAuthentication`, `CookieApiKeyAuthentication`, `BasicAuthentication` | vstavaná autorizácia | [Autorizácia](/sk/guide/authorization) |
| `Authorization\ApiAuthorizationInterface`, `TokenAuthorization` | vlastná autorizácia | [Autorizácia](/sk/guide/authorization#vlastna-autorizacia) |
| `Misc\TokenRepositoryInterface`, `StaticTokenRepository` | tokeny a ich IP | [Autorizácia](/sk/guide/authorization#tokeny-a-obmedzenie-ip) |
| `Misc\IpDetectorInterface`, `IpDetector`, `StaticIpDetector` | IP klienta | [Autorizácia](/sk/guide/authorization#tokeny-a-obmedzenie-ip) |
| `RateLimit\RateLimitInterface`, `RateLimitResponse`, `NoRateLimit` | obmedzenie požiadaviek | [Obmedzenie požiadaviek](/sk/guide/rate-limit) |

## Logovanie, odkazy a konzola

| Trieda | Čo robí | Príručka |
| --- | --- | --- |
| `Logger\ApiLoggerInterface` | log požiadaviek | [Logovanie](/sk/guide/logging) |
| `Link\ApiLink`, `ApiLinkExtension`, `ApiLinkNode` | URL a tag `{apiLink}` | [Odkazy](/sk/guide/links) |
| `Component\ApiListingControl`, `ApiConsoleControl`, `ApiConsoleFormFactoryInterface`, `DefaultApiConsoleFormFactory` | webová konzola | [Webová konzola](/sk/guide/console) |
| `Misc\ConsoleRequest`, `ConsoleResponse` | cURL požiadavka konzoly | [Webová konzola](/sk/guide/console) |
| `Misc\CorsOriginAllowlist` | interné (`@internal`), používa ho `setCorsAllowedOrigins()`; vo 4.0 sa môže zmeniť | [CORS](/sk/guide/cors#povolene-originy) |
| `Misc\OpenApiTransform` | pomocné funkcie pre OpenAPI | [OpenAPI](/sk/guide/openapi) |

## Časté otázky

### Ktoré triedy sú final?

`Presenters\ApiPresenter`, `Error\DefaultErrorHandler`, Latte triedy `Link\ApiLinkExtension` a `Link\ApiLinkNode` a interná `Misc\CorsOriginAllowlist` sú `final`; namiesto dedenia ich obaľte. Pozrite si [Spracovanie chýb](/sk/guide/errors#vlastny-error-handler).

### Kde nájdem signatúry metód?

V zdrojovom kóde na [GitHube](https://github.com/tomaj/nette-api/tree/master/src); stránky príručky ukazujú tie, ktoré používate.

### Ktoré triedy sú zastarané?

Iba `setExample()` pri parametroch a výstupoch; použite `addExample()`. Staré `StaticBearerTokenRepository` a `BearerTokenRepositoryInterface` boli vo verzii 4.0 odstránené. Použite `StaticTokenRepository` a `TokenRepositoryInterface`, ktoré fungujú rovnako.

### Existuje DI rozšírenie?

Nie, všetko sa registruje ako bežné služby v NEON, pozrite si [Začíname](/sk/guide/getting-started).

### Ako vyzerá celý projekt?

Pozrite si [príklady](/sk/examples/).
