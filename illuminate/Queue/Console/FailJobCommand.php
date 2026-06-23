<?php

namespace Illuminate\Queue\Console;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use Symfony\Component\Console\Terminal;

class FailJobCommand extends WorkCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'queue:fail-job
        {key : The cache key for the job details and payload}
        {error : base64 encoded json error_get_last()}
        {connection : The name of the queue connection to work}
        {--name=default : The name of the worker}
        {--queue= : The names of the queue to work}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail a job that has caused the worker to exit via E_ERROR';

    /**
     * Cache key for the job payload and identifiers
     */
    protected string $key;

    /**
     * json
     * @see \error_get_last()
     */
    protected string $error;

    /**
     * @inheritdoc
     * @throws \Throwable
     */
    public function handle()
    {
        try {
            $this->key = $this->argument('key');
            $this->error = \base64_decode($this->argument('error'));
        } catch (\Throwable $e) {
            $this->output->writeln($e->getMessage());

            return;
        }

        // We'll listen to the processed and failed events so we can write information
        // to the console as jobs are processed, which will let the developer watch
        // which jobs are coming through a queue and be informed on its progress.
        $this->listenForEvents();

        $connection = $this->argument('connection') ?: $this->app['config']['queue.default'];

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue($connection);

        if (Terminal::hasSttyAvailable()) {
            $this->components->info(
                sprintf('Processing jobs from the [%s] %s.', $queue, str('queue')->plural(explode(',', $queue)))
            );
        }

        $this->worker
            ->setName($this->gatherWorkerOptions()->name)
            ->setCache($this->cache)
            ->deleteAndFailJob($connection, $queue, $this->key, $this->error);
    }

    /**
     * @inheritdoc
     */
    protected function gatherWorkerOptions()
    {
        return new WorkerOptions(
            $this->option('name'),
        );
    }

    /**
     * @inheritdoc
     */
    protected function listenForEvents()
    {
        $this->app['events']->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
    }
}
