<?php

namespace MacropaySolutions\Kernel\Redis\Connections;

class PhpRedisClusterConnection extends PhpRedisConnection
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelRedisConnectionsPhpRedisClusterConnection;

    /**
     * Flush the selected Redis database on all master nodes.
     *
     * @return mixed
     */
    public function flushdb()
    {
        $arguments = func_get_args();

        $async = strtoupper((string)($arguments[0] ?? null)) === 'ASYNC';

        foreach ($this->client->_masters() as $master) {
            $async
                ? $this->command('rawCommand', [$master, 'flushdb', 'async'])
                : $this->command('flushdb', [$master]);
        }
    }

    /**
     * Keep the parent's dynamic call handling; the dedicated Macroable trait would otherwise replace it.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return parent::__call($method, $parameters);
    }
}
