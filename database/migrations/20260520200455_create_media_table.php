<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('repair_jobs')->cascadeOnDelete();
            $table->foreignUlid('job_stage_id')->constrained()->cascadeOnDelete();
            $table->string('gcs_path');
            $table->string('mime_type');
            $table->string('original_filename');
            $table->string('uploaded_by');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index(['garage_id', 'job_id']);
            $table->index(['garage_id', 'job_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
