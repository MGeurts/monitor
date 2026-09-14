<?php

namespace App\Services\Stock;

use App\Jobs\CheckEanStockJob;
use App\Models\BatchRun;
use App\Models\BatchRunItem;
use Illuminate\Support\Carbon;

class BatchRunSeeder
{
    /**
     * Create the pending item rows for a batch run and dispatch a
     * CheckEanStockJob for each EAN.
     *
     * @param  list<string>  $eans
     */
    public static function seed(BatchRun $batchRun, array $eans): void
    {
        $eans = array_values(array_unique(array_filter(array_map('trim', $eans))));

        if (empty($eans)) {
            $batchRun->update([
                'status' => 'completed',
                'total' => 0,
                'finished_at' => now(),
            ]);

            return;
        }

        $now = Carbon::now();

        collect($eans)->chunk(500)->each(function ($chunk) use ($batchRun, $now) {
            $rows = $chunk->map(fn (string $ean) => [
                'batch_run_id' => $batchRun->id,
                'ean' => $ean,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            BatchRunItem::upsert($rows, ['batch_run_id', 'ean'], ['status', 'updated_at']);
        });

        $batchRun->update([
            'total' => count($eans),
            'status' => 'running',
            'started_at' => $now,
        ]);

        foreach ($eans as $ean) {
            CheckEanStockJob::dispatch($batchRun->id, $ean);
        }
    }
}
