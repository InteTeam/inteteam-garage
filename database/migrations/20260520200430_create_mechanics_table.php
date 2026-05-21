<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mechanics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('mechanic');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['garage_id', 'user_id']);
            $table->index(['garage_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
    }
};
