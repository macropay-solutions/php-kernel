<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Bus;

use MacropaySolutions\Kernel\Bus\Batch;

/**
 * Array-callable steps and batch callbacks that record what ran.
 */
class BusSteps
{
    /**
     * @var string[]
     */
    public static array $log = [];

    public function record(string $step): void
    {
        static::$log[] = $step;
    }

    public function fail(string $step): void
    {
        static::$log[] = $step;

        throw new \RuntimeException('step ' . $step . ' failed');
    }

    public static function then(Batch $batch): void
    {
        static::$log[] = 'then:' . $batch->processedJobs();
    }

    public static function catch(Batch $batch, \Throwable $e): void
    {
        static::$log[] = 'catch:' . $e->getMessage();
    }

    public static function finally(Batch $batch): void
    {
        static::$log[] = 'finally:' . ($batch->finished() ? 'finished' : 'open');
    }
}
