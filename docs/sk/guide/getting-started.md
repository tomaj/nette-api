---
title: Začíname
description: Vytvorenie Nette projektu, inštalácia tomaj/nette-api, routa /api na ApiPresenter, registrácia handlera v ApiDecider a volanie prvého endpointu.
howto:
  totalTime: PT10M
  tools: [PHP 8.3, Composer]
---

# Začíname

Táto stránka vás prevedie od nového Nette projektu k fungujúcemu endpointu `GET /api/v1/hello`. Každý krok sa dá skopírovať tak, ako je. Rovnaký endpoint ako samostatný projekt je [príklad hello world](/sk/examples/hello-world).

<Diagram name="getting-started-1" alt="Composer install, routa na Api:Api:default, ApiDecider s addApi a handler, ktorý vracia JSON" />

## Požiadavky

| Balík | Verzia |
| --- | --- |
| PHP | 8.3 alebo novšie, s rozšíreniami curl, json, session a filter |
| nette/application, nette/http | ^3.0 |
| latte/latte | ^3.0 |
| tracy/tracy | ^2.6 |
| justinrainbow/json-schema | ^5.2 |

## 0. Vytvorte projekt

Ak už Nette aplikáciu máte, tento krok preskočte. Cesty k súborom nižšie sú tie zo skeletonu `nette/web-project`.

```bash
composer create-project nette/web-project my-api
cd my-api
```

## 1. Nainštalujte knižnicu

```bash
composer require tomaj/nette-api
```

::: tip Funkcie označené „Od verzie 3.6.0“
V poslednom vydaní (3.5.0) ešte nie sú. Ak ich chcete vyskúšať pred vydaním 3.6.0, nainštalujte namiesto toho vývojovú verziu:

```bash
composer require tomaj/nette-api:dev-master
```
:::

## 2. Nasmerujte API na ApiPresenter

Všetky endpointy idú do jedného presentera, `Tomaj\NetteApi\Presenters\ApiPresenter`. V súbore `app/Core/RouterFactory.php` pridajte API routu **pred** všeobecnú routu `<presenter>/<action>`. Inak všeobecná routa zachytí `/api/...` skôr a dostanete 404 z error presentera.

```php [app/Core/RouterFactory.php]
	public static function createRouter(): RouteList
	{
		$router = new RouteList;
		// the API route must come before the catch-all route
		$router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
		$router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');
		return $router;
	}
```

Presenter číta parametre routy `version`, `package` a `apiAction`, preto ich názvy nemeňte.

## 3. Zaregistrujte služby

Konfiguráciu API dajte do samostatného súboru `config/api.neon`. Namapuje modul `Api` na knižnicu a zaregistruje služby. Sekcia `application:` sa zlúči so sekciou v `common.neon`, takže existujúce mapovanie neupravujete.

```yaml [config/api.neon]
application:
	mapping:
		Api: Tomaj\NetteApi\Presenters\*Presenter

services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
	- Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator
	- Tomaj\NetteApi\Error\DefaultErrorHandler
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(
				Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'hello'),
				App\Api\HelloHandler(),
				Tomaj\NetteApi\Authorization\NoAuthorization()
			)
```

- `ApiPresenter` je vo `vendor/`, takže ho Nette pri hľadaní presenterov v `app/` nenájde. Nette vytvára presentery len z DI kontajnera, preto ho treba zaregistrovať ako službu. Nechajte túto jednu službu s názvom `apiPresenter`; ďalšie stránky k nej pridávajú volania `setup:`.
- `ApiPresenter` potrebuje konfigurátor výstupu a obsluhu chýb. `ApiDecider` drží zoznam endpointov.
- Skeleton odsadzuje neon súbory tabulátormi. Fungujú tabulátory aj medzery, ale v jednom súbore ich nikdy nemiešajte; nette/neon taký súbor nenačíta.

Potom súbor načítajte v `app/Bootstrap.php`:

```php [app/Bootstrap.php]
	private function setupContainer(): void
	{
		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig($configDir . '/common.neon');
		$this->configurator->addConfig($configDir . '/services.neon');
		$this->configurator->addConfig($configDir . '/api.neon');
	}
```

## 4. Napíšte handler

Vytvorte `app/Api/HelloHandler.php`:

```php [app/Api/HelloHandler.php]
<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class HelloHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Say hello';
    }

    public function params(): array
    {
        return [
            (new GetInputParam('name'))->setDefault('world')->setDescription('Who to greet'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => 'Hello ' . $params['name'] . '!']);
    }
}
```

`handle()` dostane zvalidované parametre ako pole a vráti odpoveď. Ostatné metódy vysvetľujú [Handlery](/sk/guide/handlers).

## 5. Zavolajte ho

Z koreňa projektu spustite vstavaný PHP server:

```bash
php -S localhost:8000 -t www
```

V ďalšom termináli:

```bash
curl "http://localhost:8000/api/v1/hello?name=Nette"
# {"message":"Hello Nette!"}
```

Endpoint, ktorý nie je zaregistrovaný, vráti `404` s `{"status":"error","message":"Unknown api endpoint"}`.

Každá odpoveď navyše obsahuje `Access-Control-Allow-Origin: *`, pretože to je predvolené nastavenie CORS. Pred nasadením nastavte originy, ktorým dôverujete; viac v [CORS](/sk/guide/cors).

## Časté otázky

### Prečo môj endpoint vracia 404, hoci som ho zaregistroval?

Metóda, verzia, balík aj akcia sa musia zhodovať. `GET` a `POST` sú rôzne endpointy. Skontrolujte aj parametre routy; pozrite si [Architektúru](/sk/guide/architecture).

### Potrebujem pre každý endpoint vlastný presenter?

Nie, `ApiPresenter` obslúži všetky. Vy píšete [handlery](/sk/guide/handlers).

### Môže byť verzia reťazec, napríklad `2.1`?

Áno. `EndpointIdentifier` ukladá verziu ako reťazec, takže `v2.1/users` funguje. Pozrite si [Odkazy a verzie](/sk/guide/links).

### Ako pridám k endpointu autorizáciu?

Ako tretí argument `addApi()` odovzdajte iný objekt, napríklad `BearerTokenAuthorization`. Pozrite si [Autorizáciu](/sk/guide/authorization).

### Kde nájdem kompletný projekt, ktorý si môžem spustiť?

V [examples/hello-world](/sk/examples/hello-world). Každý príklad má test `e2e.sh`, ktorý spúšťa CI.
