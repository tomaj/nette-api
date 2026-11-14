<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * The request body uses a media type the handler does not accept. ApiPresenter answers 415.
 */
final class UnsupportedMediaTypeException extends \RuntimeException
{
    /**
     * @param list<string> $accepted
     */
    private function __construct(string $detail, public readonly array $accepted)
    {
        parent::__construct($detail);
    }

    /**
     * @param list<string> $accepted
     */
    public static function missing(array $accepted): self
    {
        return new self('Content-Type is missing. Use one of: ' . implode(', ', $accepted) . '.', $accepted);
    }

    /**
     * @param list<string> $accepted
     */
    public static function received(string $type, array $accepted): self
    {
        return new self(
            "Content-Type '" . $type . "' is not supported for this endpoint. Use one of: " . implode(', ', $accepted) . '.',
            $accepted,
        );
    }
}
