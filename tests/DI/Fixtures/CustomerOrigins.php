<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;

final readonly class CustomerOrigins implements CorsOriginValidatorInterface
{
    public function __construct(private string $domain = 'https://customer.example.com')
    {
    }

    public function isAllowedOrigin(string $origin): bool
    {
        return $origin === $this->domain;
    }
}
