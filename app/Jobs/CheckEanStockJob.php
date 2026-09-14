<?php

namespace App\Jobs;

use App\Models\BatchRun;
use App\Models\BatchRunItem;
use App\Services\Stock\StockAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckEanStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public int $batchRunId,
        public string $ean,
    ) {
        $this->onQueue(config('monitor.batch.queue', 'monitor'));
    }

    public function handle(StockAggregatorService $aggregator): void
    {
        $batchRun = BatchRun::find($this->batchRunId);

        // The batch run may have been deleted while this job was queued.
        if (! $batchRun) {
            return;
        }

        $check = $aggregator->check($this->ean);

        $hasError = $check['results']->contains(fn ($r) => ! empty($r['error']));
        $hasMismatch = $check['results']->contains(fn ($r) => ! $r['is_master'] && $r['found'] && (float) $r['diff'] !== 0.0);
        $description = $check['results']->firstWhere('is_master', true)['description'] ?? null;

        BatchRunItem::where('batch_run_id', $this->batchRunId)
            ->where('ean', $this->ean)
            ->update([
                'description' => $description,
                'status' => 'checked',
                'master_stock' => $check['master_stock'],
                'has_mismatch' => $hasMismatch,
                'has_error' => $hasError,
                'results' => $check['results']->toArray(),
                'checked_at' => now(),
            ]);

        DB::table('batch_runs')->where('id', $this->batchRunId)->update([
            'processed' => DB::raw('processed + 1'),
            'mismatches' => $hasMismatch ? DB::raw('mismatches + 1') : DB::raw('mismatches'),
            'errors' => $hasError ? DB::raw('errors + 1') : DB::raw('errors'),
        ]);

        $this->maybeFinishBatch();
    }

    protected function maybeFinishBatch(): void
    {
        $batchRun = BatchRun::find($this->batchRunId);

        if ($batchRun && $batchRun->processed >= $batchRun->total && $batchRun->status !== 'completed') {
            $batchRun->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }
}
