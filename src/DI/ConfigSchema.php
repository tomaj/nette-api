<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\Schema\Context;
use Nette\Schema\Message;
use Nette\Schema\Schema;

/**
 * The schema of the api section. It adds to the errors of nette/schema how to fix the value.
 *
 * @internal used by NetteApiExtension at container compile time
 */
final readonly class ConfigSchema implements Schema
{
    private const string ENDPOINT = '{method: GET, version: 1, package: users, handler: App\Api\UsersHandler}';

    private const array KEYS = [
        '' => ['outputConfigurator', 'ipDetector', 'logger', 'errors', 'cors', 'logging', 'authorizations', 'rateLimits', 'rateLimiting', 'defaults', 'discovery', 'endpoints'],
        'errors' => ['format', 'typeBaseUri', 'handler'],
        'cors' => ['mode', 'allowedOrigins', 'originValidator', 'allowCredentials', 'preflight'],
        'logging' => ['maskHeaders', 'maskQueryParams', 'masking', 'hashKey'],
        'logging.maskHeaders' => ['names', 'add', 'remove', 'patterns'],
        'logging.maskHeaders.patterns' => ['add', 'remove'],
        'logging.maskQueryParams' => ['names', 'add', 'remove'],
        'rateLimits.*' => ['algorithm', 'limit', 'interval', 'key', 'name'],
        'rateLimiting' => ['storage', 'redis', 'prefix', 'clock'],
        'defaults' => ['authorization', 'rateLimit'],
        'defaults.rateLimit' => ['algorithm', 'limit', 'interval', 'key', 'name'],
        'endpoints.*.rateLimit' => ['algorithm', 'limit', 'interval', 'key', 'name'],
        'discovery' => ['paths', 'namespace'],
        'endpoints.*' => ['method', 'version', 'package', 'action', 'handler', 'authorization', 'rateLimit'],
    ];

    private const array SERVICES = [
        'outputConfigurator' => "'env', 'debugger', 'query', a class implementing Tomaj\\NetteApi\\Output\\Configurator\\ConfiguratorInterface",
        'ipDetector' => 'a class implementing Tomaj\NetteApi\Misc\IpDetectorInterface',
        'logger' => 'a class implementing Tomaj\NetteApi\Logger\ApiLoggerInterface',
        'logging.masking' => "'full', 'partial', 'hash', 'omit', a class implementing Tomaj\\NetteApi\\Logger\\LogValueMaskerInterface",
        'errors.handler' => 'a class implementing Tomaj\NetteApi\Error\ErrorHandlerInterface',
        'cors.originValidator' => 'a class implementing Tomaj\NetteApi\Misc\CorsOriginValidatorInterface',
        'authorizations.*' => 'a class implementing Tomaj\NetteApi\Authorization\ApiAuthorizationInterface',
        'rateLimits.*' => '{algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}, a class implementing Tomaj\NetteApi\RateLimit\RateLimiterInterface',
        'rateLimiting.storage' => "'cache' (the default), 'memory', 'redis', a class implementing Tomaj\\NetteApi\\RateLimit\\Storage\\RateLimitStorageInterface",
        'rateLimiting.redis' => 'a Redis client (\Redis, \RedisCluster, Predis\ClientInterface or Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface)',
        'rateLimiting.clock' => 'a class implementing Psr\Clock\ClockInterface',
        'defaults.authorization' => 'a name from api.authorizations (leave it out for no default), a class implementing Tomaj\NetteApi\Authorization\ApiAuthorizationInterface',
        'defaults.rateLimit' => 'a name from api.rateLimits (leave it out for no default), a list of names like [perIp, perUser], {limit: 60, interval: 1 minute}, a class implementing Tomaj\NetteApi\RateLimit\RateLimiterInterface',
        'endpoints.*.handler' => 'a handler class name',
        'endpoints.*.authorization' => 'false (no authorization for this endpoint), a name from api.authorizations, a class implementing Tomaj\NetteApi\Authorization\ApiAuthorizationInterface',
        'endpoints.*.rateLimit' => 'false (no rate limit for this endpoint), a name from api.rateLimits, a list of names like [perIp, perUser], {limit: 60, interval: 1 minute}, a class implementing Tomaj\NetteApi\RateLimit\RateLimiterInterface',
    ];

    private const array VALUES = [
        '' => 'The api section is a map, for example api: {endpoints: [{endpoint}]}.',
        'errors' => 'errors is a map, for example errors: {format: problemDetails}.',
        'errors.format' => "Use one of 'default', 'problemDetails', 'negotiating'.",
        'errors.typeBaseUri' => 'Use an absolute URI, for example typeBaseUri: https://example.com/errors/.',
        'cors' => 'cors is a map, for example cors: {allowedOrigins: [https://app.example.com]}.',
        'cors.mode' => "Use '*', 'off', 'reflect-unsafe', or one origin like https://app.example.com.",
        'cors.allowedOrigins' => 'Use a list, for example allowedOrigins: [https://app.example.com].',
        'cors.allowCredentials' => 'Use true or false.',
        'cors.preflight' => "Use true, false, or a map of headers, for example preflight: {Access-Control-Max-Age: '600'}.",
        'cors.preflight.*' => "A header value is a string or a list of strings, for example Access-Control-Allow-Headers: [Content-Type, Authorization].",
        'logging' => 'logging is a map, for example logging: {maskHeaders: true}.',
        'logging.maskHeaders' => 'Use true (the default), false, a list of header names that replaces the defaults, or a map, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie], patterns: {add: [x-signature-*]}}.',
        'logging.maskHeaders.names' => 'Use a list of header names, for example names: [Authorization, X-Session].',
        'logging.maskHeaders.add' => 'Use a list of header names, for example add: [X-Session].',
        'logging.maskHeaders.remove' => 'Use a list of header names, for example remove: [Set-Cookie].',
        'logging.maskHeaders.patterns' => 'Use true (the default), false, a list of patterns that replaces the defaults, or a map, for example patterns: {add: [x-signature-*], remove: [*key*]}.',
        'logging.maskHeaders.patterns.add' => 'Use a list of patterns, for example add: [x-signature-*].',
        'logging.maskHeaders.patterns.remove' => 'Use a list of patterns, for example remove: [*key*].',
        'logging.maskQueryParams' => 'Use true (the default), false, a list of parameter names that replaces the defaults, or a map, for example maskQueryParams: {add: [session_id], remove: [code]}.',
        'logging.maskQueryParams.names' => 'Use a list of parameter names, for example names: [api_key, token].',
        'logging.maskQueryParams.add' => 'Use a list of parameter names, for example add: [session_id].',
        'logging.maskQueryParams.remove' => 'Use a list of parameter names, for example remove: [code].',
        'logging.hashKey' => 'Use a string, for example hashKey: %env.API_LOG_HASH_KEY%.',
        'authorizations' => 'Use a map of name: class, for example authorizations: {bearer: App\Api\MyAuthorization}.',
        'rateLimits' => 'Use a map of name: limit, for example rateLimits: {perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}.',
        'rateLimits.*.algorithm' => "Use one of 'fixedWindow', 'slidingWindow' (the default), 'tokenBucket', or a class implementing Tomaj\\NetteApi\\RateLimit\\Algorithm\\RateLimitAlgorithmInterface.",
        'rateLimits.*.limit' => 'Use a whole number of requests per interval, for example limit: 60.',
        'rateLimits.*.interval' => "Use seconds or a text like '30 seconds', '1 minute', '1 hour', '1 day', for example interval: 1 minute.",
        'rateLimits.*.key' => 'Use ip, identity, endpoint, a class implementing Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface, or a list like [identity, endpoint].',
        'rateLimits.*.name' => 'Use a policy name of letters, digits, _, - and ., for example name: perIp.',
        'rateLimiting' => 'rateLimiting is a map, for example rateLimiting: {storage: redis, redis: @redis}.',
        'rateLimiting.prefix' => 'Use a string, for example prefix: myapp:rate-limit:.',
        'defaults' => 'defaults is a map, for example defaults: {authorization: bearer}.',
        'discovery' => 'discovery is a map, for example discovery: {paths: [%appDir%/Api]}.',
        'discovery.paths' => 'Use a list of directories, for example paths: [%appDir%/Api].',
        'discovery.namespace' => 'Use a namespace, for example namespace: App\Api.',
        'endpoints' => 'Use a list of endpoints, for example endpoints: [{endpoint}].',
        'endpoints.*' => 'An endpoint is a map, for example - {endpoint}.',
        'endpoints.*.method' => 'Use an HTTP method, for example method: GET.',
        'endpoints.*.version' => 'Use a number or a string, for example version: 1.',
        'endpoints.*.package' => 'Use one URL segment, for example package: users.',
        'endpoints.*.action' => 'Use one URL segment, for example action: detail.',
    ];

    public function __construct(
        private Schema $schema,
    ) {
    }

    public function normalize(mixed $value, Context $context): mixed
    {
        return $this->schema->normalize($value, $context);
    }

    public function merge(mixed $value, mixed $base): mixed
    {
        return $this->schema->merge($value, $base);
    }

    public function complete(mixed $value, Context $context): mixed
    {
        // the extension has its own context, so all its errors are known only here, after the whole config is validated
        $value = $this->schema->complete($value, $context);
        $this->addHints($context);
        return $value;
    }

    public function completeDefault(Context $context): mixed
    {
        return $this->schema->completeDefault($context);
    }

    private function addHints(Context $context): void
    {
        foreach ($context->errors as $message) {
            $hint = $this->hint($message);
            if ($hint !== null) {
                // a variable, because older nette/schema replaces unknown %placeholders% like %appDir% in the message
                $message->message .= ' %fixHint%';
                $message->variables['fixHint'] = $hint;
            }
        }
    }

    /**
     * The key of the item without the extension name, with "*" for list items and names: "endpoints.*.method"
     *
     * @param list<int|string> $path
     */
    private static function key(array $path): string
    {
        $key = [];
        foreach (array_slice($path, 1) as $index => $segment) {
            $parent = $key[$index - 1] ?? null;
            $key[] = in_array($parent, ['endpoints', 'authorizations', 'rateLimits', 'preflight'], true) ? '*' : $segment;
        }
        return implode('.', $key);
    }

    /**
     * The neon key as written in other errors of the extension: "api.endpoints[0]"
     *
     * @param list<int|string> $path
     */
    private static function where(array $path): string
    {
        $where = 'api';
        foreach (array_slice($path, 1) as $segment) {
            $where .= is_int($segment) ? '[' . $segment . ']' : '.' . $segment;
        }
        return $where;
    }

    /**
     * An inline rate limit of an endpoint or of the defaults has the same items as one in api.rateLimits
     */
    private static function rateLimitKey(string $key): string
    {
        return (string) preg_replace('~^(?:endpoints\.\*|defaults)\.rateLimit\.(?=\w)~', 'rateLimits.*.', $key);
    }

    private function hint(Message $message): ?string
    {
        $path = $message->path;
        if ($message->code === Message::UnexpectedItem) {
            $parent = self::key(array_slice($path, 0, -1));
            $keys = self::KEYS[$parent] ?? null;
            return $keys === null ? null : sprintf('Allowed keys in %s: %s.', self::where(array_slice($path, 0, -1)), implode(', ', $keys));
        }
        if ($message->code === Message::MissingItem && self::rateLimitKey(self::key($path)) !== self::key($path)) {
            return 'A rate limit needs limit and interval, for example {limit: 60, interval: 1 minute}.';
        }
        if ($message->code === Message::MissingItem) {
            return 'Every endpoint needs method, version, package and handler, for example - ' . self::ENDPOINT . '.';
        }

        $key = self::rateLimitKey(self::key($path));
        if (!empty($message->variables['isKey'])) {
            return self::VALUES[self::key(array_slice($path, 0, -1))] ?? null;
        }
        if (isset(self::SERVICES[$key])) {
            return sprintf('Use %s, @serviceName, or a statement like App\MyClass(arguments).', self::SERVICES[$key]);
        }
        $hint = self::VALUES[$key] ?? null;
        if ($hint === null) {
            return null;
        }
        $hint = str_replace('{endpoint}', self::ENDPOINT, $hint);
        $value = $message->variables['value'] ?? null;
        if ($key === 'errors.format' && is_string($value)) {
            $hint = ltrim(Hint::didYouMean($value, ['default', 'problemDetails', 'negotiating']) . ' ' . $hint);
        }
        if ($key === 'cors.mode' && is_bool($value)) {
            // neon 3.x reads an unquoted off, no, on or yes as a boolean
            $hint = "Neon read the mode as a boolean (an unquoted off, no, on or yes), write it in quotes, for example mode: 'off'. " . $hint;
        }
        return $hint;
    }
}
