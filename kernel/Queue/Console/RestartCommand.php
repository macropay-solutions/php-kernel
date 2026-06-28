<?php

namespace MacropaySolutions\Kernel\Queue\Console;

use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;
use MacropaySolutions\Kernel\Support\InteractsWithTime;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:restart')]
class RestartCommand extends Command
{
    use InteractsWithTime;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart queue worker daemons after their current job';

    /**
     * The cache store implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new queue restart command.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Cache\Repository $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->forever('kernel:queue:restart', $this->currentTime());

        $this->components->info('Broadcasting queue restart signal.');
    }
}
