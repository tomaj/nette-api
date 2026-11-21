<?php

declare(strict_types=1);

namespace App;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function create(): RouteList
    {
        $router = new RouteList();
        // {id} in users/{id}/detail is one more segment, captured by <params>
        $router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
        return $router;
    }
}
