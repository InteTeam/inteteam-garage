<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mechanic_on_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained('mechanics')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['garage_id', 'starts_at']);
            $table->index(['garage_id', 'ends_at']);
            $table->index(['garage_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_on_calls');
    }
};
