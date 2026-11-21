<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Invalid client input inside a caster. The message is returned as a 400 under the property path.
 */
final class CastException extends \RuntimeException
{
}
