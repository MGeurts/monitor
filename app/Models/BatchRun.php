<?php

namespace App\Models;

use Database\Factories\BatchRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchRun extends Model
{
    /** @use HasFactory<BatchRunFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'total',
        'processed',
        'mismatches',
        'errors',
        'source',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BatchRunItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BatchRunItem::class);
    }

    public function progressPercentage(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round(($this->processed / $this->total) * 100);
    }
}
