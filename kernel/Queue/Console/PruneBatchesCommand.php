<?php

namespace MacropaySolutions\Kernel\Queue\Console;

use MacropaySolutions\Kernel\Bus\BatchRepository;
use MacropaySolutions\Kernel\Bus\DatabaseBatchRepository;
use MacropaySolutions\Kernel\Bus\PrunableBatchRepository;
use MacropaySolutions\Kernel\Console\Command;
use MacropaySolutions\Kernel\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:prune-batches')]
class PruneBatchesCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:prune-batches
        {--hours=24 : The number of hours to retain batch data}
        {--unfinished= : The number of hours to retain unfinished batch data }
        {--cancelled= : The number of hours to retain cancelled batch data }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale entries from the batches database';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $repository = $this->app[BatchRepository::class];

        $count = 0;

        if ($repository instanceof PrunableBatchRepository) {
            $count = $repository->prune(Carbon::now()->subHours($this->option('hours')));
        }

        $this->components->info("{$count} entries deleted.");

        if ($this->option('unfinished') !== null) {
            $count = 0;

            if ($repository instanceof DatabaseBatchRepository) {
                $count = $repository->pruneUnfinished(Carbon::now()->subHours($this->option('unfinished')));
            }

            $this->components->info("{$count} unfinished entries deleted.");
        }

        if ($this->option('cancelled') !== null) {
            $count = 0;

            if ($repository instanceof DatabaseBatchRepository) {
                $count = $repository->pruneCancelled(Carbon::now()->subHours($this->option('cancelled')));
            }

            $this->components->info("{$count} cancelled entries deleted.");
        }
    }
}
