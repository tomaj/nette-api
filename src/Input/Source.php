<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Where a DTO property is read from.
 */
enum Source: string
{
    case Query = 'query';
    case Path = 'path';
    case Header = 'header';
    case Body = 'body';
    case File = 'file';
}
