<?php

namespace App\Jobs;

use App\Models\BatchRun;
use App\Services\Stock\BatchRunSeeder;
use App\Services\Stock\SourceRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SeedBatchRunFromErpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $batchRunId,
    ) {
        $this->onQueue(config('monitor.batch.queue', 'monitor'));
    }

    public function handle(SourceRegistry $sources): void
    {
        $batchRun = BatchRun::find($this->batchRunId);

        if (! $batchRun) {
            return;
        }

        $erp = $sources->master();
        $eans = method_exists($erp, 'fetchAllEans') ? $erp->fetchAllEans() : [];

        BatchRunSeeder::seed($batchRun, $eans);
    }
}
