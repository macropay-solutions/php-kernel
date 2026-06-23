<?php

namespace Illuminate\Cache;

class LuaScripts
{
    /**
     * Get the Lua script to atomically release a lock.
     *
     * KEYS[1] - The name of the lock
     * ARGV[1] - The owner key of the lock instance trying to release it
     *
     * @return string
     */
    public static function releaseLock()
    {
        return <<<'LUA'
if redis.call("get",KEYS[1]) == ARGV[1] then
    return redis.call("del",KEYS[1])
else
    return 0
end
LUA;
    }

    /**
     * Get the Lua script to atomically refresh a lock's expiration.
     *
     * KEYS[1] - The name of the lock
     * ARGV[1] - The owner key of the lock instance trying to refresh it
     * ARGV[2] - The number of seconds the lock should be valid
     */
    public static function refreshLock(): string
    {
        return <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    if tonumber(ARGV[2]) > 0 then
        return redis.call("expire", KEYS[1], ARGV[2])
    end

    redis.call("persist", KEYS[1])

    return 1
end

return 0
LUA;
    }
}
