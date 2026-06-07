<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('repair_jobs')->cascadeOnDelete();
            $table->string('submitted_by_token');
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_inspections');
    }
};
