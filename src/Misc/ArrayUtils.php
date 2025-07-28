<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Closure;

/**
 * Array utilities demonstrating PHP 8.4 features
 */
final readonly class ArrayUtils
{
    /**
     * Find first API endpoint that matches criteria using PHP 8.4's array_find
     */
    public static function findApiEndpoint(array $endpoints, Closure $criteria): mixed
    {
        return array_find($endpoints, $criteria);
    }

    /**
     * Find the key of first matching API endpoint using PHP 8.4's array_find_key
     */
    public static function findApiEndpointKey(array $endpoints, Closure $criteria): mixed
    {
        return array_find_key($endpoints, $criteria);
    }

    /**
     * Check if any API endpoint matches criteria using PHP 8.4's array_any
     */
    public static function hasApiEndpoint(array $endpoints, Closure $criteria): bool
    {
        return array_any($endpoints, $criteria);
    }

    /**
     * Check if all API endpoints match criteria using PHP 8.4's array_all
     */
    public static function allApiEndpointsMatch(array $endpoints, Closure $criteria): bool
    {
        return array_all($endpoints, $criteria);
    }

    /**
     * Filter endpoints by HTTP method (demonstrating new syntax)
     */
    public static function filterByMethod(array $endpoints, string $method): array
    {
        return array_filter(
            $endpoints,
            static fn($endpoint) => $endpoint->getMethod() === strtoupper($method)
        );
    }

    /**
     * Group endpoints by version using modern PHP features
     */
    public static function groupByVersion(array $endpoints): array
    {
        $grouped = [];
        
        foreach ($endpoints as $endpoint) {
            $version = $endpoint->getVersion();
            $grouped[$version] ??= [];
            $grouped[$version][] = $endpoint;
        }
        
        return $grouped;
    }

    /**
     * Get first and last elements using helper methods
     */
    public static function getFirstEndpoint(array $endpoints): mixed
    {
        return array_key_first($endpoints) !== null ? $endpoints[array_key_first($endpoints)] : null;
    }

    public static function getLastEndpoint(array $endpoints): mixed
    {
        return array_key_last($endpoints) !== null ? $endpoints[array_key_last($endpoints)] : null;
    }

    /**
     * Validate endpoint collection using modern syntax
     */
    public static function validateEndpoints(array $endpoints): ValidationResult
    {
        $errors = [];

        // Check if any endpoints are invalid
        if (array_any($endpoints, static fn($endpoint) => !$endpoint instanceof \Tomaj\NetteApi\EndpointInterface)) {
            $errors[] = 'Some endpoints do not implement EndpointInterface';
        }

        // Check if all endpoints have valid methods
        if (!array_all($endpoints, static fn($endpoint) => in_array($endpoint->getMethod(), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true))) {
            $errors[] = 'Some endpoints have invalid HTTP methods';
        }

        return empty($errors) 
            ? new ValidationResult(\Tomaj\NetteApi\ValidationResult\ValidationStatus::OK)
            : new ValidationResult(\Tomaj\NetteApi\ValidationResult\ValidationStatus::ERROR, $errors);
    }
}