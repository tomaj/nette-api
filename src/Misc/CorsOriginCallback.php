<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * @internal used by ApiPresenter::setCorsOriginValidator(), may change in 4.x
 *
 * Origins allowed by a validator called at request time. Only an Origin header in the form browsers send
 * (scheme://host[:port]) reaches the validator, an origin it allows is reflected in Access-Control-Allow-Origin.
 */
final readonly class CorsOriginCallback implements CorsOriginPolicy
{
    private const string USAGE = 'Pass an object implementing Tomaj\NetteApi\Misc\CorsOriginValidatorInterface, or a callable like fn (string $origin): bool => in_array($origin, $origins, true).';

    private Closure $validator;

    /**
     * @param mixed $validator CorsOriginValidatorInterface or callable(string $origin): bool
     */
    public function __construct(mixed $validator, private bool $allowCredentials = false)
    {
        if ($validator instanceof CorsOriginValidatorInterface) {
            $this->validator = $validator->isAllowedOrigin(...);
            return;
        }

        if (!is_callable($validator)) {
            throw new InvalidArgumentException(sprintf(
                'The CORS origin validator must be callable, %s given%s. %s',
                get_debug_type($validator),
                self::why($validator),
                self::USAGE,
            ));
        }

        $this->validator = Closure::fromCallable($validator);
        $function = new ReflectionFunction($this->validator);
        if ($function->getNumberOfRequiredParameters() > 1) {
            throw new InvalidArgumentException(sprintf(
                'The CORS origin validator requires %d parameters, but it gets only the origin. %s',
                $function->getNumberOfRequiredParameters(),
                self::USAGE,
            ));
        }
        $type = $function->getReturnType();
        if ($type instanceof ReflectionNamedType && $type->getName() !== 'mixed' && ($type->allowsNull() || !in_array($type->getName(), ['bool', 'true', 'false'], true))) {
            throw new InvalidArgumentException(sprintf(
                'The CORS origin validator returns %s, but it must return bool: true allows the origin, false rejects it. %s',
                $type,
                self::USAGE,
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function headers(?string $requestOrigin): array
    {
        if ($requestOrigin === null || !CorsOriginAllowlist::isOrigin($requestOrigin)) {
            return ['Vary' => 'Origin'];
        }

        $allowed = ($this->validator)($requestOrigin);
        if (!is_bool($allowed)) {
            throw new UnexpectedValueException(sprintf(
                "The CORS origin validator returned %s for origin '%s', but it must return true (allow the origin) or false (reject it). Return a bool, for example return in_array(\$origin, \$origins, true);",
                get_debug_type($allowed),
                $requestOrigin,
            ));
        }
        if (!$allowed) {
            return ['Vary' => 'Origin'];
        }

        $headers = ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => $requestOrigin];
        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    private static function why(mixed $validator): string
    {
        if (is_string($validator)) {
            return str_contains($validator, '::')
                ? sprintf(" ('%s' is not an existing public static method)", $validator)
                : sprintf(" ('%s' is not an existing function; a list of origins goes to setCorsAllowedOrigins())", $validator);
        }
        if (is_array($validator) && array_filter($validator, static fn (mixed $item): bool => is_string($item) && str_contains($item, '://')) !== []) {
            return ' (a list of origins goes to setCorsAllowedOrigins())';
        }
        if (is_array($validator) && array_is_list($validator) && count($validator) === 2 && (is_object($validator[0]) || is_string($validator[0])) && is_string($validator[1])) {
            return sprintf(' (method %s::%s() does not exist or is not public)', is_object($validator[0]) ? $validator[0]::class : $validator[0], $validator[1]);
        }
        if (is_object($validator)) {
            return sprintf(' (%s has no __invoke() method and does not implement CorsOriginValidatorInterface)', $validator::class);
        }
        return '';
    }
}
