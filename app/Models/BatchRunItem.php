<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchRunItem extends Model
{
    /** @use HasFactory<\Database\Factories\BatchRunItemFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_run_id',
        'ean',
        'description',
        'status',
        'master_stock',
        'has_mismatch',
        'has_error',
        'results',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'has_mismatch' => 'boolean',
            'has_error' => 'boolean',
            'master_stock' => 'float',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BatchRun, $this>
     */
    public function batchRun(): BelongsTo
    {
        return $this->belongsTo(BatchRun::class);
    }
}
