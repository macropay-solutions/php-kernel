<?php

namespace Illuminate\Console\Events;

class RunStarting
{
    /**
     * The console application instance.
     *
     * @var \Illuminate\Console\Application
     */
    public $consoleApp;

    /**
     * Create a new event instance.
     *
     * @param \Illuminate\Console\Application $consoleApp
     * @return void
     */
    public function __construct($consoleApp)
    {
        $this->consoleApp = $consoleApp;
    }
}
