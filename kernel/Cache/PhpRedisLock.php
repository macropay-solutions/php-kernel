<?php

namespace MacropaySolutions\Kernel\Cache;

use MacropaySolutions\Kernel\Redis\Connections\PhpRedisConnection;

class PhpRedisLock extends RedisLock
{
    /**
     * Create a new phpredis lock instance.
     *
     * @param \MacropaySolutions\Kernel\Redis\Connections\PhpRedisConnection $redis
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     */
    public function __construct(PhpRedisConnection $redis, string $name, int $seconds, ?string $owner = null)
    {
        parent::__construct($redis, $name, $seconds, $owner);
    }

    /**
     * {@inheritDoc}
     */
    public function release()
    {
        return (bool)$this->redis->eval(
            LuaScripts::releaseLock(),
            1,
            $this->name,
            ...$this->redis->pack([$this->owner])
        );
    }

    public function refresh(?int $seconds = null): bool
    {
        // only the owner is compared to a stored value: a packed (serialized) TTL is not an integer for EXPIRE
        [$owner] = $this->redis->pack([$this->owner]);

        return (bool)$this->redis->eval(
            LuaScripts::refreshLock(),
            1,
            $this->name,
            $owner,
            $seconds ?? $this->seconds
        );
    }
}
