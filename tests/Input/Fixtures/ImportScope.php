<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures;

use function strlen;
use const PHP_INT_MAX;
use \Myfunction\Widget;
use \Tomaj\NetteApi\Validation\Email AS EmailAlias;
use Tomaj\NetteApi\Input\{ Query as Q , Body };
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Validation\Range as RangeAlias ;
use Tomaj\NetteApi\Validation;

// use NotImported\Ghost;

final class ImportScope
{
    use ImportScopeTrait;

    public function keep(): string
    {
        $value = 'x';
        $capture = function () use ($value): string {
            return $value;
        };

        return EmailAlias::class . Q::class . Body::class . strlen('a') . PHP_INT_MAX . $capture() . Validation\Email::class;
    }
}
