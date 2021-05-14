<?php

namespace Tomaj\NetteApi\Handlers;

interface AdditionalDataHandlerInterface
{
    /**
     * List of additional information for handler
     *
     * @return array<string, int|string|bool|array>
     */
    public function additionalData(): array;
}
