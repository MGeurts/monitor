<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ean_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_source_id')->constrained()->cascadeOnDelete();
            $table->string('ean');
            $table->string('external_product_id');
            $table->string('sku')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_source_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_ean_maps');
    }
};
