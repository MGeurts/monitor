<?php

namespace App\Livewire\Stock;

use App\Jobs\SeedBatchRunFromErpJob;
use App\Models\BatchRun;
use App\Services\Stock\BatchRunSeeder;
use App\Services\Stock\SourceRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Batch opzoeken')]
class BatchRunPage extends Component
{
    use WithPagination;

    #[Url]
    public ?int $batchRunId = null;

    public string $eanSource = 'erp'; // erp, paste

    public string $pastedEans = '';

    public string $filter = 'all'; // all, mismatches, errors, pending

    public function mount(): void
    {
        if (! $this->batchRunId) {
            $this->batchRunId = BatchRun::query()->latest()->value('id');
        }
    }

    public function startFromErp(): void
    {
        $batchRun = BatchRun::create([
            'user_id' => auth()->id(),
            'status' => 'pending',
            'source' => 'erp',
        ]);

        SeedBatchRunFromErpJob::dispatch($batchRun->id);

        $this->batchRunId = $batchRun->id;
        $this->filter = 'all';
    }

    public function startFromPaste(): void
    {
        $this->validate([
            'pastedEans' => ['required', 'string'],
        ]);

        $pieces = preg_split('/[\s,;]+/', $this->pastedEans) ?: [];

        $eans = array_values(
            collect($pieces)
                ->map(fn (string $ean): string => trim($ean))
                ->filter()
                ->all()
        );

        $batchRun = BatchRun::create([
            'user_id' => auth()->id(),
            'status' => 'pending',
            'source' => 'paste',
        ]);

        BatchRunSeeder::seed($batchRun, $eans);

        $this->batchRunId = $batchRun->id;
        $this->pastedEans = '';
        $this->filter = 'all';
    }

    #[Computed]
    public function batchRun(): ?BatchRun
    {
        return $this->batchRunId ? BatchRun::find($this->batchRunId) : null;
    }

    #[Computed]
    public function recentRuns(): Collection
    {
        return BatchRun::query()->latest()->limit(10)->get();
    }

    #[Computed]
    public function isLive(): bool
    {
        return $this->batchRun() && in_array($this->batchRun()->status, ['pending', 'running'], true);
    }

    public function render(SourceRegistry $sources): View
    {
        $items = null;

        if ($this->batchRun()) {
            $items = $this->batchRun()->items()
                ->when($this->filter === 'mismatches', fn ($q) => $q->where('has_mismatch', true))
                ->when($this->filter === 'errors', fn ($q) => $q->where('has_error', true))
                ->when($this->filter === 'pending', fn ($q) => $q->where('status', 'pending'))
                ->orderByDesc('has_mismatch')
                ->orderBy('ean')
                ->paginate(25);
        }

        return view('livewire.stock.batch-run-page', [
            'items' => $items,
            'sourceMeta' => $sources->meta(),
        ]);
    }
}
