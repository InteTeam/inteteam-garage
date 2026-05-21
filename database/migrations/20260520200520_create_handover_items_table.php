<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('handover_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('line_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['handover_inspection_id', 'line_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_items');
    }
};
