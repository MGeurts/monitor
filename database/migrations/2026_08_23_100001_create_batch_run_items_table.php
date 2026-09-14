<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_run_id')->constrained()->cascadeOnDelete();
            $table->string('ean')->index();
            $table->string('description')->nullable();
            $table->string('status')->default('pending'); // pending, checked, failed
            $table->decimal('master_stock', 12, 2)->nullable();
            $table->boolean('has_mismatch')->default(false);
            $table->boolean('has_error')->default(false);
            $table->json('results')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_run_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_run_items');
    }
};
