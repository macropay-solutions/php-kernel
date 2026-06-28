<?php

namespace MacropaySolutions\Kernel\Database\Events;

use MacropaySolutions\Kernel\Contracts\Database\Events\MigrationEvent as MigrationEventContract;
use MacropaySolutions\Kernel\Database\Migrations\Migration;

abstract class MigrationEvent implements MigrationEventContract
{
    /**
     * A migration instance.
     *
     * @var \MacropaySolutions\Kernel\Database\Migrations\Migration
     */
    public $migration;

    /**
     * The migration method that was called.
     *
     * @var string
     */
    public $method;

    /**
     * Create a new event instance.
     *
     * @param \MacropaySolutions\Kernel\Database\Migrations\Migration $migration
     * @param string $method
     * @return void
     */
    public function __construct(Migration $migration, $method)
    {
        $this->method = $method;
        $this->migration = $migration;
    }
}
