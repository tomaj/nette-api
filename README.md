# Nette-Api

**Nette simple api library**

[![Build Status](https://travis-ci.org/tomaj/nette-api.svg)](https://travis-ci.org/tomaj/nette-api)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/nette-api.svg)](https://packagist.org/packages/tomaj/nette-api)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/b0a43dba-eb81-42de-b043-95ef51b8c097/big.png)](https://insight.sensiolabs.com/projects/b0a43dba-eb81-42de-b043-95ef51b8c097)

## Why Nette-Api

This library provides out-of-the box API solution for Nette framework. You can register API endpoints and connect it to specified handlers. You need only implement your custom business logic. Library provides authorization, validation, rate limit and formatting services for you API.

## Installation

This library requires PHP 7.1 or later.

Recommended installation method is via Composer:

```bash
composer require tomaj/nette-api
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## How Nette-API works

First, you have to register library presenter for routing. In *config.neon* just add this line:

```neon
application:
  mapping:
    Api: Tomaj\NetteApi\Presenters\*Presenter
```

Register your preferred output configurator in *config.neon* services: 

```neon
services:
    apiOutputConfigurator: Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator
```

Register your preferred error handler in *config.neon* services: 

```neon
services:
    apiErrorHandler: Tomaj\NetteApi\Error\DefaultErrorHandler
```

And add route to you RouterFactory:

```php
$router[] = new Route('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
```

If you want to use RESTful urls you will need another route:
```php
$router[] = new Route('api/v<version>/<package>/<id>', [
    'presenter' => 'Api:Api',
    'action' => 'default',
    'id' => [
        Route::FILTER_IN => function ($id) {
            $_GET['id'] = $id;
            return $id;
        }
    ],
]);
```

After that you need only register your API handlers to *apiDecider* [ApiDecider](src/ApiDecider.php), register [ApiLink](src/Link/ApiLink.php) and [Tomaj\NetteApi\Misc\IpDetector](src/Misc/IpDetector.php). This can be done also with *config.neon*:

```neon
services:
    - Tomaj\NetteApi\Link\ApiLink
    - Tomaj\NetteApi\Misc\IpDetector
    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\NoAuthorization())
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users', 'send-email'), \App\MyApi\v1\Handlers\SendEmailHandler(), \Tomaj\NetteApi\Authorization\BearerTokenAuthorization())

```

or lazy (preferred because of performance)
```neon
services:
    - App\MyApi\v1\Handlers\SendEmailLazyHandler()
    sendEmailLazyNamed: App\MyApi\v1\Handlers\SendEmailLazyNamedHandler()
    
    factory: Tomaj\NetteApi\ApiDecider
    setup:
      - addApi(\Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users', 'send-email-lazy'), 'App\MyApi\v1\Handlers\SendEmailHandler', \Tomaj\NetteApi\Authorization\BearerTokenAuthorization())
      - addApi(\Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users', 'send-email-lazy-named'), '@sendEmailLazyNamed', \Tomaj\NetteApi\Authorization\BearerTokenAuthorization())
```

As you can see in example, you can register as many endpoints as you want with different configurations. Nette-Api supports API versioning from the beginning.
This example will prepare these API calls:

1. `http://yourapp/api/v1/users` - available via GET
2. `http://yourapp/api/v1/users/send-email`  - available via POST


Core of the Nette-Api are handlers. For this example you need to implement two classes:

1. App\MyApi\v1\Handlers\UsersListingHandler
2. App\MyApi\v1\Handlers\SendEmailHandler

These handlers implement interface *[ApiHandlerInterface](src/Handlers/ApiHandlerInterface.php)* but for easier usage you can extend your handlers from [BaseHandler](src/Handlers/BaseHandler.php). 
When someone reach your API, these handlers will be triggered and *handle()* method will be called.

```php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersListingHandler extends Basehandler
{
    private $userRepository;

    public function __construct(UsersRepository $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
    }

    public function handle(array $params): ResponseInterface
    {
        $users = [];
        foreach ($this->userRepository->all() as $user) {
            $users[] = $user->toArray();
        }
        return new JsonApiResponse(200, ['status' => 'ok', 'users' => $users]);
    }
}
```

This simple handler is using *UsersRepository* that was created by Nette Container (so you have to register your *App\MyApi\v1\Handlers\UsersListingHandler* in config.neon).

## Advanced use (with Fractal)

Nette-Api provides integration with [Fractal][] library for formatting API responses.
If you want to use it, you have to extend your handler from *[BaseHandler](src/Handlers/BaseHandler.php)* and your Fractal instance will be accessible by `$this->getFractal()`.

Main advantage of Fractal is separation of your API "view" (like transformation data to json object or xml or anything...). Also you can include transformations in other transformations to include other objects to others. 

Example with fractal:

1. You will need Transformer

```php
namespace App\MyApi\v1\Transformers;

use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
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

```php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersListingHandler extends Basehandler
{
    private $userTransformer;

    public function __construct(UserTransformer $userTransformer)
    {
        parent::__construct();
        $this->userTransformer = $userTransformer;
    }

    public function handle(array $params): ResponseInterface
    {
        $users = $this->useRepository->all(); 

        $resource = new Collection($users, $this->userTransformer);
        $result = $this->getFractal()->createData($resource)->toArray();

        return new JsonApiResponse(200, $result);
    }
}
```

We recommend to take a look at [Fractal][] library. There are much more information about transformers, serializers, paginations etc. It is really nice library.

[Fractal]: http://fractal.thephpleague.com/


## ApiLink in latte

First, you have to register filter in *config.neon*:

```neon
services:
    apiLink: Tomaj\NetteApi\Link\ApiLink()
    latte.latteFactory:
        setup:
            - addFilter(apiLink, [@apiLink, link])

```
**Note**: Name of filter has to be `apiLink`, because it is used in macro / extension.

For latte < 3.0 register latte macro:
```neon
latte:
    macros:
        - Tomaj\NetteApi\Link\ApiLinkMacro
```

For latte >= 3.0 register latte extension:
```neon
latte:
    extensions:
        - Tomaj\NetteApi\Link\ApiLinkExtension
```


Usage in latte files:

```
{apiLink $method, $version, $package, $apiAction, ['title' => 'My title', 'data-foo' => 'bar']}
```

## Endpoint inputs

Each handler can describe which input is required. It could be GET or POST parameters, also COOKIES, raw post, JSON or file uploads. You have to implement method `params()` where you have to return array with params. These params are used in API console to generate form.

Example with user detail:

```php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersDetailHandler extends Basehandler
{
    private $userRepository;

    public function __construct(UsersRepository $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->userRepository->find($params['id']);
        if (!$user) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
        }
        return new JsonApiResponse(200, ['status' => 'ok', 'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }
}
```

## Input Types

Nette-Api provides various InputParam types. You can send params with GET, POST, COOKIES, FILES, RAW POST data or JSON.
All input types are available via test console.

This is table with supported input types:

| Input type      | Example
| ----------      | -------
| GET             | `new GetInputParam('key')`
| POST            | `new PostInputParam('key')`
| COOKIE          | `new CookieInputParam('key')`
| FILE            | `new FileInputParam('key')`
| RAW POST        | `new RawInputParam('key')`
| JSON            | `new JsonInputParam('key', '{"type": "object"}')`


## Outputs

By implementing method outputs for your handlers you can specify list of possible outputs (e.g. output schemas) and those will be validated before response is sent to user. If no outputs are set, response is sent to user without validating.

Usage example:
```php
public function outputs(): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
            ],
            'surname' => [
                'type' => 'string',
            ],
            'sex' => [
                'type' => 'string',
                'enum' => ['M', 'F'],
            ],
        ],
        'required' => ['name', 'surname'],
        'additionalProperties' => false,
    ];
    return [
        new JsonOutput(200, json_encode($schema)),
    ];
}
```
For more examples see [JSON schema web page](http://json-schema.org). Keep in mind that nette api uses [justinrainbow/json-schema](https://github.com/justinrainbow/json-schema/tree/master/dist/schema) for validating schemas and this package supports only json schema draft 03 and 04.


## Security

Protecting your API is easy with Nette-Api. You have to implement your [Authorization](src/Authorization/ApiAuthorizationInterface.php) (Tomaj\NetteApi\Authorization\ApiAuthorizationInterface) or use prepared implementations and add it as third argument to *addApi()* method in *config.neon*.

### Basic authentication
Basic authentication is a simple authentication scheme built into the HTTP protocol. It contains username and password. You can define as many pairs of usernames and passwords as you want. But just one password for each username.

```neon
services:
    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\BasicBasicAuthentication(['first-user': 'first-password', 'second-user': 'second-password']))
```


### Bearer token authentication
For simple use of Bearer token authorization with few tokens, you can use [StaticTokenRepository](src/Misc/StaticTokenRepository.php) (Tomaj\NetteApi\Misc\StaticTokenRepository).

```neon
services:
    staticTokenRepository: Tomaj\NetteApi\Misc\StaticTokenRepository(['dasfoihwet90hidsg': '*', 'asfoihweiohgwegi': '127.0.0.1'])

    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\BearerTokenAuthorization(@staticTokenRepository))
```

With this registration you will have api `/api/v1/users` that will be accessible from anywhere with Authorisation HTTP header `Bearer dasfoihwet90hidsg` or from *127.0.0.1* with `Bearer asfoihweiohgwegi`.
In Nette-Api if you would like to specify IP restrictions for tokens you can use this patterns:

| IP Pattern                | Access
| ----------                | ------
|`*`                        | accessible from anywhere
|`127.0.0.1`                | accessible from single IP
|`127.0.0.1,127.0.02`       | accessible from multiple IP, separator could be new line or space
|`127.0.0.1/32`             | accessible from ip range
|*null*                     | token is disabled, cannot access 


But it is very easy to implement your own Authorisation for API.

### API keys
You can also use API keys for authorization. An API key is a token that a client provides when making API calls. The key can be sent in the query string, header or cookie. See examples below:

```neon
services:
    staticTokenRepository: Tomaj\NetteApi\Misc\StaticTokenRepository(['dasfoihwet90hidsg': '*', 'asfoihweiohgwegi': '127.0.0.1'])

    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'query'), Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication('api_key', @staticTokenRepository))
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'header'), Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication('X-API-KEY', @staticTokenRepository))
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'cookie'), Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication('api_key', @staticTokenRepository))
```

## Rate limit

This library provides simple interface for API rate limit. All you need to do is implement this interface like in example below:

```php

use Nette\Application\Responses\TextResponse;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

class MyRateLimit implements RateLimitInterface
{
    public function check(): ?RateLimitResponse
    {
        // do some logic here

        // example outputs:
        
        return null;    // no rate limit

        return new RateLimitResponse(60, 50);   // remains 50 of 60 hits
    
        return new RateLimitResponse(60, 0, 120);   // remains 0 of 60 hits, retry after 120 seconds

        return new RateLimitResponse(60, 0, 120, new TextResponse('My custom error message'));  // remains 0 of 60 hits, retry after 120 seconds, with custom TextResponse (default is Json response, see ApiPresenter::checkRateLimit())
    }
}
```

Then you have to register API to ApiDecider with Rate Limit

```neon
services:
    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), \Tomaj\NetteApi\Authorization\BearerTokenAuthorization(@staticTokenRepository), MyRateLimit())
```

## Javascript ajax calls (CORS - preflight OPTIONS calls)

If you need to call API via javascript ajax from other domains, you will need to prepare API for [preflight calls with OPTIONS method](https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS).
Nette-api is ready for this situation and you can choose if you want to enable pre-flight calls globally or you can register prepared prefligt handlers.

Globally enabled - every api endpoint will be available for preflight OPTIONS call:

```neon
services:
    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - enableGlobalPreflight()
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())
```

Or you can register custom OPTIONS endpoints:

```neon
services:
    apiDecider:
        factory: Tomaj\NetteApi\ApiDecider
        setup:
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('OPTIONS', 1, 'users'), \Tomaj\NetteApi\Handlers\CorsPreflightHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())
            - addApi(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())
            
```

## Logging

It is good practice to log you api access if you provide valuable information with your API. To enable logging you need to implement class with interface [ApiLoggerInterface](src/Logger/ApiLoggerInterface.php) (Tomaj\NetteApi\Logger\ApiLoggerInterface) and register it as service in *config.neon*. It will be automatically wired and called after execution of all API requests.

## CORS Security

If you need to iteract with your API with Javascript you will need to send correct CORS headers. [ApiPresenter](src/Presenters/ApiPresenter.php) has property to set this headers. By default api will send header **'Access-Control-Allow-Origin'** with value *'*'*. If you need to change it you can set property $corsHeader to values:

1. *'auto'* - send back header Access-Control-Allow-Origin with domain that made request. It is not secure, but you can acces this api from other domains via AJAX
2. *'*'* - send header with '*' - this will work fine if you dont need to send cookies via ajax calls to api with jquery *$.ajax with xhrFields: { withCredentials: true }* settings
3. *'off'* - will not send any CORS header
5. other - any other value will be send in *Access-Control-Allow-Origin* header

You can set this property in config.neon if you register [ApiPresenter](src/Presenters/ApiPresenter.php):

```neon
services:
  -
    factory: Tomaj\NetteApi\Presenters\ApiPresenter
    setup:
      - setCorsHeader('auto')
```

or if you extend [ApiPresenter](src/Presenters/ApiPresenter.php), than you can set it on your own presenter.


# WEB console - API tester

Nette-Api contains 2 UI controls that can be used to validate you api.
It will generate listing with all API calls and also auto generate form with all api params.

All components generate bootstrap html and can be styled with bootstrap css:

You have to create components in your controller:

```php
use Nette\Application\UI\Presenter;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Component\ApiConsoleControl;
use Tomaj\NetteApi\Component\ApiListingControl;
use Tomaj\NetteApi\Link\ApiLink;

class MyPresenter extends Presenter
{
    private $apiDecider;

    private $apiLink;

    private $method;
    
    private $version;
    
    private $package;
    
    private $apiAction;

    public function __construct(ApiDecider $apiDecider, ApiLink $apiLink = null)
    {
        parent::__construct();
        $this->apiDecider = $apiDecider;
        $this->apiLink = $apiLink;
    }

    public function renderShow(string $method, int $version, string $package, ?string $apiAction = null): void
    {
        $this->method = $method;
        $this->version = $version;
        $this->package = $package;
        $this->apiAction = $apiAction;
    }

    protected function createComponentApiListing(): ApiListingControl
    {
        $apiListing = new ApiListingControl($this->apiDecider);
        $apiListing->onClick[] = function ($method, $version, $package, $apiAction) {
            $this->redirect('show', $method, $version, $package, $apiAction);
        };
        return $apiListing;
    }

    protected function createComponentApiConsole()
    {
        $api = $this->apiDecider->getApi($this->method, $this->version, $this->package, $this->apiAction);
        $apiConsole = new ApiConsoleControl($this->getHttpRequest(), $api->getEndpoint(), $api->getHandler(), $api->getAuthorization(), $this->apiLink);
        return $apiConsole;
    }
}
```

## Troubleshooting

If your apache server is runing on CGI or fastCGI script, *$_SERVER['HTTP_AUTHORIZATION']* is empty.
You'll need to do some mod_rewrite wizardry to get your headers past the CGI barrier, like so:

```
RewriteEngine on
RewriteRule .? - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

```bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information 
