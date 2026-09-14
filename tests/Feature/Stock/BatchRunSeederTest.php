<?php

use App\Jobs\CheckEanStockJob;
use App\Models\BatchRun;
use App\Models\BatchRunItem;
use App\Services\Stock\BatchRunSeeder;
use Illuminate\Support\Facades\Queue;

it('deduplicates pasted EANs, creates pending rows and queues one job per EAN', function () {
    Queue::fake();
    $run = BatchRun::create(['source' => 'paste']);

    BatchRunSeeder::seed($run, [' 6937186640697 ', '6937186640697', '8719505566424']);

    $run = $run->fresh();

    expect($run->status)->toBe('running')
        ->and($run->total)->toBe(2)
        ->and($run->processed)->toBe(0);

    expect(BatchRunItem::where('batch_run_id', $run->id)->count())->toBe(2);
    Queue::assertPushed(CheckEanStockJob::class, 2);
});

it('completes an empty batch immediately without dispatching a job', function () {
    Queue::fake();
    $run = BatchRun::create(['source' => 'paste']);

    BatchRunSeeder::seed($run, []);

    $run = $run->fresh();

    expect($run->status)->toBe('completed')
        ->and($run->total)->toBe(0)
        ->and($run->finished_at)->not->toBeNull();

    Queue::assertNothingPushed();
});
