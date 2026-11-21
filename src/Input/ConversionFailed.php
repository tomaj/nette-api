<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * A value could not be converted to the property type. The message is safe to show to the client.
 */
final class ConversionFailed extends \RuntimeException
{
}
