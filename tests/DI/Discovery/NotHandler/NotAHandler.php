<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\NotHandler;

use Tomaj\NetteApi\Attribute\Get;

#[Get(1, 'nope')]
final class NotAHandler
{
}
