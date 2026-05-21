<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_job_mechanic', function (Blueprint $table) {
            $table->foreignUlid('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['repair_job_id', 'mechanic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_job_mechanic');
    }
};
