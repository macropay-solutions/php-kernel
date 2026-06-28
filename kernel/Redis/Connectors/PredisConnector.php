<?php

namespace MacropaySolutions\Kernel\Redis\Connectors;

use MacropaySolutions\Kernel\Contracts\Redis\Connector;
use MacropaySolutions\Kernel\Redis\Connections\PredisClusterConnection;
use MacropaySolutions\Kernel\Redis\Connections\PredisConnection;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Str;
use Predis\Client;

class PredisConnector implements Connector
{
    /**
     * Create a new connection.
     *
     * @param array $config
     * @param array $options
     * @return \MacropaySolutions\Kernel\Redis\Connections\PredisConnection
     */
    public function connect(array $config, array $options)
    {
        $formattedOptions = array_merge(
            ['timeout' => 10.0],
            $options,
            Arr::pull($config, 'options', [])
        );

        if (isset($config['prefix'])) {
            $formattedOptions['prefix'] = $config['prefix'];
        }

        if (isset($config['host']) && str_starts_with($config['host'], 'tls://')) {
            $config['scheme'] = 'tls';
            $config['host'] = Str::after($config['host'], 'tls://');
        }

//        return new PredisConnection(new Client($config, $formattedOptions));
        return \di(PredisConnection::class, [\di(Client::class, [$config, $formattedOptions])]);
    }

    /**
     * Create a new clustered Predis connection.
     *
     * @param array $config
     * @param array $clusterOptions
     * @param array $options
     * @return \MacropaySolutions\Kernel\Redis\Connections\PredisClusterConnection
     */
    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        $clusterSpecificOptions = Arr::pull($config, 'options', []);

        if (isset($config['prefix'])) {
            $clusterSpecificOptions['prefix'] = $config['prefix'];
        }

//        return new PredisClusterConnection(new Client(array_values($config), array_merge(
//            $options, $clusterOptions, $clusterSpecificOptions
//        )));
        return \di(PredisClusterConnection::class, [
            \di(Client::class, [
                array_values($config),
                array_merge($options, $clusterOptions, $clusterSpecificOptions),
            ]),
        ]);
    }
}
