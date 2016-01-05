# nette-api

### work in progress - in early development mode

**Nette simple api library**

[![Build Status](https://travis-ci.org/tomaj/nette-api.svg)](https://travis-ci.org/tomaj/nette-api)
[![Dependency Status](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7/badge.svg?style=flat)](https://www.versioneye.com/user/projects/567d3b10a7c90e002c0003a7)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tomaj/nette-api/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tomaj/nette-api/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/nette-api.svg)](https://packagist.org/packages/tomaj/nette-api)

## Why Nette-api

This library provides out-of-the box API solution for Nette framework. You can register API endpoints and connect it to specified handlers. You need only implement you custom busines logic. Library provide autorization, validation and formating services for you api.

## Installation

This library requires PHP 5.4 or later. It works also on HHVM and PHP 7.0.

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

As you can see in example, you can register as many endpoints as you want with different configuration. Nette-api support api version from the beggining.
This example will prepare this api calls:

1. `http://yourapp/api/v1/users` - available via GET
2. `http://yourapp/api/v1/users/send-email`  - available via POST


Core of the nette api is handlers. For this example you need implement 2 classes:

1. App\MyApi\v1\Handlers\UsersListingHandler
2. App\MyApi\v1\Handlers\SendEmailHandler

This handlers implements interface *[ApiHandlerInterface](src/Handlers/ApiHandlerInterface.php)* but for easier usage you can extens your handler from [BaseHandler](src/Handlers/BaseHandler.php). 
When someone reach your api this handlers will be triggered and *handle()* method will be called.

``` php
namespace App\MyApi\v1\Handlers;

use Tomaj\NetteApi\Handlers\BaseHandler;

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
		$users = [] 
		foreach ($this->useRepository->all() as $user) {
			$users[] = $user->toArray();
		}
		return new ApiResponse(200, ['status' => 'ok', 'users' => $users]);
	}
}
```

This simple handler is usign *UsersRepository* that was created by Nette Container (so you have to register your *App\MyApi\v1\Handlers\UsersListingHandler* in config.neon).

## Advanced use (with Fractal)

NetteApi provides integration with [Fractal][] library for formatting API responses.
If you want to use it you have to extend your handler from *[BaseHandler](src/Handlers/BaseHandler.php)* and your Fractal class will be accesible be `$this->getFractal()`.

Main advantage that you will gain when you use Fractal is separation you api "view" like transformation data to json object (or xml or anything...). Also you can include transformators in other transformators to include other objects to others. 

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

        return new ApiResponse(200, $result);
    }
}
```

I have to recomment to take a look at Fractal library (http://fractal.thephpleague.com/)[http://fractal.thephpleague.com/]. There are much more information about transformers, serializers, paginations etc. It is really nice library.

[Fractal]: http://fractal.thephpleague.com/

## Security

Protecting your api is easy with NetteApi. You need only implement your [Authorization](src/Authorization/ApiAuthorizationInterface.php) (Tomaj\NetteApi\Authorization\ApiAuthorizationInterface) and add it as third argument to *addApiHandler()* method in *config.neon*.

For simple use, if you want to use Bearer token authorization with few tokens, you can use [StaticBearerTokenRepository](src/Misc/StaticBearerTokenRepository.php) (Tomaj\NetteApi\Misc\StaticBearerTokenRepository).

``` yaml
services:
    staticBearer: Tomaj\NetteApi\Misc\StaticBearerTokenRepository(['dasfoihwet90hidsg' => '*', 'asfoihweiohgwegi' => '127.0.0.1'])
    
    apiDecider:
        class: Tomaj\NetteApi\ApiDecider
        setup:
            - addApiHandler(\Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), \App\MyApi\v1\Handlers\UsersListingHandler(), @staticBearer)
    
```

With this registration you will have api `/api/v1/users` that will be accesible from anywhare with Authorization HTTP header `Bearer dasfoihwet90hidsg` or from *127.0.0.1* with `Bearer asfoihweiohgwegi`.
In NetteApi if you would like to specify IP restrictions for tokens you can use this patterns:

| IP Pattern                | Access
| ----------                | ------
|`*`                        | accessible from anywhare
|`127.0.0.1`                | accessible from single IP
|`127.0.0.1,127.0.02`       | accessible from multiple IP, separator could be new line or space
|`127.0.0.1/32`             | accessible from ip range


But it is very easy to implement your own Authorization for API.

## Logging

It is good practice to log you api access if you provide valuable information with your API. To enable logging you need to implement class with interface [ApiLoggerInterface](src/Logger/ApiLoggerInterface.php) (Tomaj\NetteApi\Logger\ApiLoggerInterface) and register it as service in *config.neon*. It will be automatically wired and called after execution of all api requests.

# WEB console - API tester

Nette-api contains 2 UI controls that can be used to validate you api.
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

