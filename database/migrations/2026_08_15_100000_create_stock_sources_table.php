<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_sources', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // backed by App\Enums\StockSourceType
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->text('credentials')->nullable(); // encrypted json: consumer_key/secret, client_id/secret, api_key/secret...
            $table->string('base_url')->nullable(); // per-shop base URL, only used by WooCommerce
            $table->json('meta')->nullable(); // per-source extra settings, e.g. ean_meta_key for WooCommerce
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_sources');
    }
};
