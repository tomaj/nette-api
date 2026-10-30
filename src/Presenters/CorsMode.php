<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Presenters;

/**
 * Predefined CORS modes for ApiPresenter::setCorsHeader().
 * ApiPresenter::setCorsHeader() also accepts one origin (scheme://host[:port]), sent as the Access-Control-Allow-Origin value.
 */
enum CorsMode: string
{
    /**
     * Sends Access-Control-Allow-Origin: *
     */
    case Any = '*';

    /**
     * Sends no CORS header
     */
    case Off = 'off';

    /**
     * UNSAFE: sends back Access-Control-Allow-Origin with the origin taken from the Referer header together with
     * Access-Control-Allow-Credentials: true (and Vary: Origin, Referer), so ANY website can read responses with the user's cookies.
     * This is the 'auto' mode of 3.x; prefer ApiPresenter::setCorsAllowedOrigins().
     */
    case ReflectUnsafe = 'reflect-unsafe';
}
