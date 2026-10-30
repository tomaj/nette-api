<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Logger;

/**
 * How LogValueMasker replaces a masked header or query parameter value in the API log
 */
enum LogMaskMode: string
{
    /** the value becomes *** (the default) */
    case Full = 'full';

    /** the first 4 characters stay, the rest becomes ***; values shorter than 12 characters are fully masked, an auth scheme like "Bearer " is kept */
    case Partial = 'partial';

    /** the value becomes hash:<16 hex characters> of SHA-256 (or HMAC-SHA-256 with a key), to correlate requests with the same secret */
    case Hash = 'hash';

    /** the header or query parameter is left out of the log */
    case Omit = 'omit';
}
