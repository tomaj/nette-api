<?php

declare(strict_types=1);

namespace App\Model;

final class UserRepository
{
    /** @var array<int, array{id: int, name: string}> */
    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice'],
        2 => ['id' => 2, 'name' => 'Bob'],
    ];

    /**
     * @return list<array{id: int, name: string}>
     */
    public function all(): array
    {
        return array_values($this->users);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function find(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }
}
