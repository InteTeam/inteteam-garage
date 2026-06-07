<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->string('crm_customer_id');
            $table->string('registration');
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('colour')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['garage_id', 'registration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
