# Nette-Api

**Nette simple api library**

[![Build Status](https://travis-ci.org/tomaj/nette-api.svg)](https://travis-ci.org/tomaj/nette-api)
[![Dependency Status](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7/badge.svg?style=flat)](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/nette-api.svg)](https://packagist.org/packages/tomaj/nette-api)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/b0a43dba-eb81-42de-b043-95ef51b8c097/big.png)](https://insight.sensiolabs.com/projects/b0a43dba-eb81-42de-b043-95ef51b8c097)

## Why Nette-Api

This library provides out-of-the box API solution for Nette framework. You can register API endpoints and connect it to specified handlers. You need only implement you custom business logic. Library provide authorisation, validation and formatting services for you api.

## Installation

This library requires PHP 5.4 or later. It works also on PHP 7.0.

Recommended installation method is via Composer:

``` bash
$ composer require tomaj/nette-api
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## How Nette-API works

First you have register library presenter for routing. In *config.neon* just add this line:

``` yaml
application:
  mapping:
    Api: Tomaj\NetteApi\Presenters\*Presenter
```

And add route to you RouterFactory:

``` php
$router[] = new Route('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
```

After that you need only register your api handlers to *apiDecider* [ApiDecider](src/ApiDecider.php) and register [ApiLink](src/Link/ApiLink.php) and [Tomaj\NetteApi\Misc\IpDetector](src/Misc/IpDetector.php). This can be done also with *config.neon*:

``` yaml
services:
  - Tomaj\NetteApi\Link\ApiLink
  - Tomaj\NetteApi\Misc\IpDetector
  apiDecider:
    class: Tomaj\NetteApi\ApiDecider
      setup:
        - addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\NoAuthorization())
        - addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users', 'send-email'), \App\MyApi\v1\Handlers\SendEmailHandler(), \Tomaj\NetteApi\Authorization\BearerTokenAuthorization())
```

As you can see in example, you can register as many endpoints as you want with different configurations. Nette-Api support api versioning from the beginning.
This example will prepare this api calls:

1. `http://yourapp/api/v1/users` - available via GET
2. `http://yourapp/api/v1/users/send-email`  - available via POST


Core of the Nette-Api is handlers. For this example you need implement 2 classes:

1. App\MyApi\v1\Handlers\UsersListingHandler
2. App\MyApi\v1\Handlers\SendEmailHandler

This handlers implements interface *[ApiHandlerInterface](src/Handlers/ApiHandlerInterface.php)* but for easier usage you can extends your handler from [BaseHandler](src/Handlers/BaseHandler.php). 
When someone reach your api this handlers will be triggered and *handle()* method will be called.

``` php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersListingHandler extends Basehandler
{
    private $userRepository;

    public function __construct(UsersRepository $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
    }

    public function handle($params)
    {
        $users = [];
        foreach ($this->useRepository->all() as $user) {
            $users[] = $user->toArray();
        }
        return new JsonApiResponse(200, ['status' => 'ok', 'users' => $users]);
    }
}
```

This simple handler is using *UsersRepository* that was created by Nette Container (so you have to register your *App\MyApi\v1\Handlers\UsersListingHandler* in config.neon).

## Advanced use (with Fractal)

Nette-Api provides integration with [Fractal][] library for formatting API responses.
If you want to use it, you have to extend your handler from *[BaseHandler](src/Handlers/BaseHandler.php)* and your Fractal instance will be accessible be `$this->getFractal()`.

Main advantage from Fractal is separation your api "view" (like transformation data to json object or xml or anything...). Also you can include transformations in other transformations to include other objects to others. 

Example with fractal:

1. You will need Formater

``` php
namespace App\MyApi\v1\Transformers;

use League\Fractal\TransformerAbstract;

class LoginTransformer extends TransformerAbstract
{
    public function transform($user)
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }
}
```

2. And this will be your handler:

``` php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersListingHandler extends Basehandler
{
    private $loginTransformer;

    public function __construct(LoginTransformer $loginTransformer)
    {
        parent::__construct();
        $this->loginTransformer = $loginTransformer;
    }

    public function handle($params)
    {
        $users = $this->useRepository->all(); 

        $resource = new Collection($users, $this->loginTransformer);
        $result = $this->getFractal()->createData($resource)->toArray();

        return new JsonApiResponse(200, $result);
    }
}
```

I have to recommend to take a look at Fractal library (http://fractal.thephpleague.com/)[http://fractal.thephpleague.com/]. There are much more information about transformers, serialisers, paginations etc. It is really nice library.

[Fractal]: http://fractal.thephpleague.com/

## Input Types

Nette-Api provides various InputParam types. You can send params with GET, POST, COOKIES, FILES or RAW POST data.
All input types are available via test console.

This is table with support input types:

| Input type      | Example
| ----------      | -------
| POST            | `new InputParam(InputParam::TYPE_POST, 'key')`
| GET             | `new InputParam(InputParam::TYPE_GET, 'key')`
| FILE            | `new InputParam(InputParam::TYPE_FILE, 'key')`
| COOKIE          | `new InputParam(InputParam::TYPE_COOKIE, 'key')`
| RAW POST        | `new InputParam(InputParam::TYPE_COOKIE, 'key')`

## Security

Protecting your api is easy with Nette-Api. You have to implement your [Authorization](src/Authorization/ApiAuthorizationInterface.php) (Tomaj\NetteApi\Authorization\ApiAuthorizationInterface) and add it as third argument to *addApiHandler()* method in *config.neon*.

For simple use, if you want to use Bearer token authorisation with few tokens, you can use [StaticBearerTokenRepository](src/Misc/StaticBearerTokenRepository.php) (Tomaj\NetteApi\Misc\StaticBearerTokenRepository).

``` yaml
services:
    staticBearer: Tomaj\NetteApi\Misc\StaticBearerTokenRepository(['dasfoihwet90hidsg' => '*', 'asfoihweiohgwegi' => '127.0.0.1'])

    apiDecider:
        class: Tomaj\NetteApi\ApiDecider
        setup:
            - addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), @staticBearer)

```

With this registration you will have api `/api/v1/users` that will be accessible from anywhere with Authorisation HTTP header `Bearer dasfoihwet90hidsg` or from *127.0.0.1* with `Bearer asfoihweiohgwegi`.
In Nette-Api if you would like to specify IP restrictions for tokens you can use this patterns:

| IP Pattern                | Access
| ----------                | ------
|`*`                        | accessible from anywhere
|`127.0.0.1`                | accessible from single IP
|`127.0.0.1,127.0.02`       | accessible from multiple IP, separator could be new line or space
|`127.0.0.1/32`             | accessible from ip range
|*false*                    | token is disabled, cannot access 


But it is very easy to implement your own Authorisation for API.

## Logging

It is good practice to log you api access if you provide valuable information with your API. To enable logging you need to implement class with interface [ApiLoggerInterface](src/Logger/ApiLoggerInterface.php) (Tomaj\NetteApi\Logger\ApiLoggerInterface) and register it as service in *config.neon*. It will be automatically wired and called after execution of all api requests.

## CORS Security

If you need to iteract with your API with Javascript you will need to send correct CORS headers. [ApiPresenter](src/Presenters/ApiPresenter.php) has property to set this headers. By default api will send header **'Access-Control-Allow-Origin'** with value *'*'*. If you need to change it you can set property $corsHeader to values:

1. *'auto'* - send back header Access-Control-Allow-Origin with domain that made request. It is not secure, but you can acces this api from other domains via AJAX
2. *'*'* - send header with '*' - this will work fine if you dont need to send cookies via ajax calls to api with jquery *$.ajax with xhrFields: { withCredentials: true }* settings
3. *'off'* - will not send any CORS header
5. other - any other value will be send in *Access-Control-Allow-Origin* header

You can set this property in config.neon if you register [ApiPresenter](src/Presenters/ApiPresenter.php):

``` yaml
services:
  -
    class: Tomaj\NetteApi\Presenters\ApiPresenter
    setup:
      - setCorsHeader('auto')
```

or if you extend [ApiPresenter](src/Presenters/ApiPresenter.php), than you can set it on your own presenter.


# WEB console - API tester

Nette-Api contains 2 UI controls that can be used to validate you api.
It will generate listing with all API calls and also auto generate form with all api params.

All components generate bootstrap html and can be styled with bootstrap css:

You have to create components in your controller:

``` php
use Nette\Application\UI\Presenter;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Component\ApiConsoleControl;
use Tomaj\NetteApi\Component\ApiListingControl;

class MyPresenter extends Presenter
{
    private $apiDecider;

    public function __construct(ApiDecider $apiDecider)
    {
        parent::__construct();
        $this->apiDecider = $apiDecider;
    }

    public function renderShow($method, $version, $package, $apiAction)
    {
    }

    protected function createComponentApiListing()
    {
        $apiListing = new ApiListingControl($this, 'apiListingControl', $this->apiDecider);
        $apiListing->onClick(function ($method, $version, $package, $apiAction) {
            $this->redirect('show', $method, $version, $package, $apiAction);
        });
        return $apiListing;
    }

    protected function createComponentApiConsole()
    {
        $api = $this->apiDecider->getApiHandler($this->params['method'], $this->params['version'], $this->params['package'], isset($this->params['apiAction']) ? $this->params['apiAction'] : null);
        $apiConsole = new ApiConsoleControl($this->getHttpRequest(), $api['endpoint'], $api['handler'], $api['authorization']);
        return $apiConsole;
    }
}
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information 
