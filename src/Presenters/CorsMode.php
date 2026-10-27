<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Presenters;

/**
 * Predefined CORS modes for ApiPresenter::setCorsHeader().
 * Any other string passed to setCorsHeader() is sent as the Access-Control-Allow-Origin value.
 */
enum CorsMode: string
{
    /**
     * Sends back Access-Control-Allow-Origin with the domain taken from the Referer header together with
     * Access-Control-Allow-Credentials: true. This allows credentialed requests from ANY website,
     * prefer ApiPresenter::setCorsAllowedOrigins().
     */
    case Auto = 'auto';

    /**
     * Sends Access-Control-Allow-Origin: *
     */
    case Any = '*';

    /**
     * Sends no CORS header
     */
    case Off = 'off';
}
