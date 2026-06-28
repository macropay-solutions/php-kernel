<?php

namespace MacropaySolutions\Kernel\Console\Events;

class RunStarting
{
    /**
     * The console application instance.
     *
     * @var \MacropaySolutions\Kernel\Console\Application
     */
    public $consoleApp;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Console\Application $consoleApp
     * @return void
     */
    public function __construct($consoleApp)
    {
        $this->consoleApp = $consoleApp;
    }
}
