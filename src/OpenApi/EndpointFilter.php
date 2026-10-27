<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Misc\TypeHelper;

/**
 * Decides which endpoints are included in the generated spec
 */
final readonly class EndpointFilter
{
    /**
     * @param array<string|int, mixed> $allowedEndpoints - list of endpoint (path and methods) which should be included in output, empty means all, wildcard * is supported for path
     *      Example: ['user/*' => ['get', 'post', 'delete'], 'user/list' => 'post', 'user/list' => [], 'user/list']
     */
    public function __construct(private array $allowedEndpoints = [])
    {
    }

    public function isAllowed(string $path, string $method): bool
    {
        if (empty($this->allowedEndpoints)) {
            return true;
        }

        foreach ($this->allowedEndpoints as $allowedPath => $allowedMethods) {
            if (is_int($allowedPath)) { // ['user/list'] format
                $pattern = str_replace('*', '.*?', preg_quote(strtolower(TypeHelper::toString($allowedMethods))));
                if (preg_match("#$pattern#", strtolower($path))) {
                    return true;
                }

                continue;
            }

            // ['user/*' => ['get', 'post'], 'user/list' => 'post', 'user/list' => [], 'user/list' => '*'] format
            $pattern = str_replace('*', '.*?', preg_quote(strtolower($allowedPath)));
            if (preg_match("#$pattern#", strtolower($path))) {
                if (empty($allowedMethods)) {
                    return true;
                }
                if (is_string($allowedMethods) && strtolower($allowedMethods) === strtolower($method)) {
                    return true;
                }
                if (is_array($allowedMethods) && in_array(strtolower($method), array_map(static fn (mixed $allowedMethod): string => strtolower(TypeHelper::toString($allowedMethod)), $allowedMethods), true)) {
                    return true;
                }
            }
        }
        return false;
    }
}
