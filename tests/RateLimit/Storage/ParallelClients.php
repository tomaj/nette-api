<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\Assert;

/**
 * Runs 8 processes with 25 requests each at the same moment against a limit of 50, returns how many passed
 */
final class ParallelClients
{
    /**
     * @param string $type phpredis, predis or file
     * @param string $target host:port of Redis or the directory of the FileStorage
     */
    public static function run(string $type, string $target, string $prefix, string $algorithm): int
    {
        $now = (string) (floor(time() / 3600) * 3600 + 1);
        $processes = [];
        for ($i = 0; $i < 8; $i++) {
            $command = [PHP_BINARY, __DIR__ . '/storage-worker.php', $type, $target, $prefix, $algorithm, '25', $now];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            Assert::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }
        $accepted = 0;
        foreach ($processes as [$process, $pipes]) {
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            proc_close($process);
            Assert::assertMatchesRegularExpression('~^\d+$~', $output, $errors);
            $accepted += (int) $output;
        }

        return $accepted;
    }
}
