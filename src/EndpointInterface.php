<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

interface EndpointInterface
{
    public function getMethod(): string;

    public function getVersion(): string;

    public function getPackage(): string;

    public function getApiAction(): ?string;

    public function getUrl(): string;
}
