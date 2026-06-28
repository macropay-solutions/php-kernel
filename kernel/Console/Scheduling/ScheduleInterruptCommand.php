<?php

namespace MacropaySolutions\Kernel\Console\Scheduling;

use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Contracts\Cache\Repository as Cache;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schedule:interrupt')]
class ScheduleInterruptCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:interrupt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interrupt the current schedule run';

    /**
     * The cache store implementation.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new schedule interrupt command.
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
        $this->cache->put('kernel:schedule:interrupt', true, \appDate()->now()->endOfMinute());

        $this->components->info('Broadcasting schedule interrupt signal.');
    }
}
