<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained('repair_jobs')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order');
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['garage_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_stages');
    }
};
