<?php

namespace App\Models;

use App\Enums\StockSourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'label',
        'is_active',
        'credentials',
        'base_url',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockSourceType::class,
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
            'meta' => 'array',
        ];
    }

    public function eanMaps(): HasMany
    {
        return $this->hasMany(ProductEanMap::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, StockSourceType $type): Builder
    {
        return $query->where('type', $type);
    }
}
