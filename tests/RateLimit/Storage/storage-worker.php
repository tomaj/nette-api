<?php

declare(strict_types=1);

use Nette\Caching\Storages\FileStorage;
use Predis\Client;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

// One of the parallel clients of ParallelClientsTrait, prints the accepted count
require __DIR__ . '/../../../vendor/autoload.php';

[, $type, $target, $prefix, $algorithm, $calls, $now] = $argv;
if ($type === 'file') {
    $storage = new NetteCacheStorage(new FileStorage($target), $prefix, $target . '/locks');
} else {
    [$host, $port] = explode(':', $target);
    if ($type === 'phpredis') {
        $client = new Redis();
        $client->connect($host, (int) $port, 2.0);
    } else {
        $client = new Client(['host' => $host, 'port' => (int) $port]);
    }
    $storage = new RedisStorage($client, $prefix);
}
$class = 'Tomaj\\NetteApi\\RateLimit\\Algorithm\\' . ucfirst($algorithm);
$accepted = 0;
for ($i = 0; $i < (int) $calls; $i++) {
    $accepted += (int) $storage->consume(new $class(), 'shared', 50, 3600, 1, (float) $now)->accepted;
}
echo $accepted;
