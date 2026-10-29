---
title: Getting started
description: Create a Nette project, install tomaj/nette-api, route /api to ApiPresenter, register a handler in ApiDecider and call your first endpoint.
howto:
  totalTime: PT10M
  tools: [PHP 8.4, Composer]
---

# Getting started

This page takes you from a new Nette project to a working `GET /api/v1/hello` endpoint. Every step can be copied as it is. The same endpoint as a standalone project is the [hello world example](/examples/hello-world).

<Diagram name="getting-started-1" alt="Composer install, a route to Api:Api:default, ApiDecider with addApi, and a handler returning JSON" />

## Requirements

| Package | Version |
| --- | --- |
| PHP | 8.4 or newer, with the curl, json, session and filter extensions |
| nette/application | ^3.2 |
| nette/http | ^3.3 |
| latte/latte | ^3.0.20 |
| tracy/tracy | ^2.10 |
| justinrainbow/json-schema | ^6.0 |

::: tip Nette API 3.x
These are the 4.0 requirements. Nette API 3.x runs on PHP 8.3 with `nette/application` and `nette/http` ^3.0, `tracy/tracy` ^2.6 and `justinrainbow/json-schema` ^5.2. See [UPGRADE-4.0.md](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md).
:::

## 0. Create a project

Skip this step if you already have a Nette application. The file paths below are those of the `nette/web-project` skeleton.

```bash
composer create-project nette/web-project my-api
cd my-api
```

## 1. Install the library

```bash
composer require tomaj/nette-api
```

## 2. Route the API to ApiPresenter

All endpoints go to one presenter, `Tomaj\NetteApi\Presenters\ApiPresenter`. In `app/Core/RouterFactory.php`, add the API route **before** the catch-all `<presenter>/<action>` route. Otherwise the catch-all route takes `/api/...` first and you get a 404 from the error presenter.

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

The presenter reads the `version`, `package` and `apiAction` route parameters, so keep their names.

## 3. Register the services

Put the API configuration in its own file, `config/api.neon`. It maps the `Api` module to the library and registers the services. The `application:` section here is merged with the one in `common.neon`, so you do not edit the existing mapping.

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

- `ApiPresenter` ships in `vendor/`, so Nette's search for presenters in `app/` does not find it. Nette creates presenters only from the DI container, so it has to be registered as a service. Keep this one named `apiPresenter` service; other pages add `setup:` calls to it.
- `ApiPresenter` needs an output configurator and an error handler. `ApiDecider` holds the list of endpoints.
- The skeleton indents its neon files with tabs. Tabs or spaces both work, but never mix them in one file; nette/neon refuses to parse it.

::: tip Shorter with the DI extension (4.0.0)
Since 4.0.0 the same file can be one `api:` section of the [DI extension](/guide/di-extension) instead of the `services:` above.
:::

Then load the file in `app/Bootstrap.php`:

```php [app/Bootstrap.php]
	private function setupContainer(): void
	{
		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig($configDir . '/common.neon');
		$this->configurator->addConfig($configDir . '/services.neon');
		$this->configurator->addConfig($configDir . '/api.neon');
	}
```

## 4. Write the handler

Create `app/Api/HelloHandler.php`:

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

`handle()` gets the validated params as an array and returns a response. [Handlers](/guide/handlers) explains the other methods.

## 5. Call it

Start the built-in PHP server from the project root:

```bash
php -S localhost:8000 -t www
```

In another terminal:

```bash
curl "http://localhost:8000/api/v1/hello?name=Nette"
# {"message":"Hello Nette!"}
```

An endpoint that is not registered answers `404` with `{"status":"error","message":"Unknown api endpoint"}`.

Every response also carries `Access-Control-Allow-Origin: *`, because that is the default CORS setting. Before you deploy, set the origins you trust; see [CORS](/guide/cors).

## FAQ

### Why does my endpoint answer 404 even though I registered it?

The method, version, package and action must all match. `GET` and `POST` are different endpoints. Check the route parameters too; see [Architecture](/guide/architecture).

### Do I need a separate presenter per endpoint?

No, `ApiPresenter` serves all of them. You write [handlers](/guide/handlers).

### Can the version be a string such as `2.1`?

Yes. `EndpointIdentifier` stores the version as a string, so `v2.1/users` works. See [Links and versions](/guide/links).

### How do I add authorization to the endpoint?

Pass another object as the third argument of `addApi()`, for example `BearerTokenAuthorization`. See [Authorization](/guide/authorization).

### Where is a complete project I can run?

In [examples/hello-world](/examples/hello-world). Every example has an `e2e.sh` test that CI runs.
