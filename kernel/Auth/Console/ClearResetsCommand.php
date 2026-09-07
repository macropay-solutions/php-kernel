<?php

namespace MacropaySolutions\Kernel\Auth\Console;

use MacropaySolutions\Kernel\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'auth:clear-resets')]
class ClearResetsCommand extends Command
{
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelAuthConsoleClearResetsCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clear-resets {name? : The name of the password broker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush expired password reset tokens';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->app['auth.password']->broker($this->argument('name'))->getRepository()->deleteExpired();

        $this->info('Expired reset tokens cleared successfully.');
    }
}
