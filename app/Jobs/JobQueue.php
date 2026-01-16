<?php

namespace App\Jobs;

use Redis;

class JobQueue
{
    private ?Redis $redis;

    public function __construct(?Redis $redis)
    {
        $this->redis = $redis;
    }

    public function push(string $queue, array $payload): void
    {
        if ($this->redis === null) {
            return;
        }

        $this->redis->rpush($queue, json_encode($payload));
    }

    public function pop(string $queue): ?array
    {
        if ($this->redis === null) {
            return null;
        }

        $raw = $this->redis->lpop($queue);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function isAvailable(): bool
    {
        return $this->redis !== null;
    }
}
