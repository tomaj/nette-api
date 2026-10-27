<?php

declare(strict_types=1);

namespace App;

/**
 * Stands in for a database table, so the example has no other dependencies.
 */
final class UserRepository
{
    /** @var array<int, array{id: int, name: string, email: string, status: string}> */
    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
        2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'blocked'],
        3 => ['id' => 3, 'name' => 'Carol', 'email' => 'carol@example.com', 'status' => 'active'],
    ];

    /**
     * @param int[] $ids
     * @return list<array{id: int, name: string, email: string, status: string}>
     */
    public function find(?string $status, array $ids, int $limit): array
    {
        $users = array_filter($this->users, fn (array $user): bool =>
            ($status === null || $user['status'] === $status)
            && ($ids === [] || in_array($user['id'], $ids, true)));
        return array_slice(array_values($users), 0, $limit);
    }

    /**
     * @return array{id: int, name: string, email: string, status: string}
     */
    public function add(string $name, string $email): array
    {
        $id = max(array_keys($this->users)) + 1;
        return $this->users[$id] = ['id' => $id, 'name' => $name, 'email' => $email, 'status' => 'active'];
    }
}
