<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEanMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_source_id',
        'ean',
        'external_product_id',
        'sku',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function stockSource(): BelongsTo
    {
        return $this->belongsTo(StockSource::class);
    }
}
