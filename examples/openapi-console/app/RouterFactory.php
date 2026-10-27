<?php

declare(strict_types=1);

namespace App;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function create(): RouteList
    {
        $router = new RouteList();
        $router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
        // /console lists the endpoints, /console/show/GET/1/books opens the form for one of them
        $router->addRoute('/console[/<action>[/<method>/<version>/<package>[/<apiAction>]]]', 'Console:default');
        return $router;
    }
}
