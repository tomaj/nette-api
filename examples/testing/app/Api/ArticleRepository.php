<?php

declare(strict_types=1);

namespace App\Api;

final class ArticleRepository
{
    /** @param array<int, string> $articles id => title */
    public function __construct(private array $articles = [1 => 'Hello nette-api', 2 => 'Testing handlers'])
    {
    }

    public function find(int $id): ?string
    {
        return $this->articles[$id] ?? null;
    }
}
