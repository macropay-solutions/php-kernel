<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Database;

use MacropaySolutions\Framework\Console\Kernel;
use MacropaySolutions\Kernel\Database\Console\PruneCommand;

/**
 * model:prune is not registered by default, so the app adds it to its console kernel.
 */
class PruneTestKernel extends Kernel
{
    protected $commands = [PruneCommand::class];
}
