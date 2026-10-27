<?php

declare(strict_types=1);

namespace App;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function create(): RouteList
    {
        $router = new RouteList();
        // every API call goes to the presenter that ships with nette-api
        $router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
        return $router;
    }
}
