<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Body media types a typed handler can accept.
 */
final class MediaTypes
{
    public const string JSON = 'application/json';

    public const string FORM = 'application/x-www-form-urlencoded';

    public const string MULTIPART = 'multipart/form-data';

    /** @var list<string> */
    public const array ALL = [self::JSON, self::FORM, self::MULTIPART];

    public static function normalize(?string $header): string
    {
        if ($header === null || $header === '') {
            return '';
        }

        $separator = strstr($header, ';', true);
        return strtolower(trim($separator === false ? $header : $separator));
    }
}
