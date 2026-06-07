<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_state_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('repair_jobs')->cascadeOnDelete();
            $table->string('from_state');
            $table->string('to_state');
            $table->string('transitioned_by')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['garage_id', 'job_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_state_transitions');
    }
};
