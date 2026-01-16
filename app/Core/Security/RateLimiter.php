<?php

namespace App\Core\Security;

use Redis;

class RateLimiter
{
    private ?Redis $redis;

    public function __construct(?Redis $redis)
    {
        $this->redis = $redis;
    }

    public function tooManyAttempts(string $key, int $limit, int $ttlSeconds): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $current = $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $ttlSeconds);
        }

        return $current > $limit;
    }
}
