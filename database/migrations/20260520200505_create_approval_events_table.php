<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('repair_jobs')->cascadeOnDelete();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['garage_id', 'job_id', 'occurred_at']);
            $table->index(['garage_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_events');
    }
};
