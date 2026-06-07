<?php

declare(strict_types=1);

use App\Models\RepairJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default(RepairJob::STATE_CREATED);
            $table->string('payment_reference')->nullable();
            $table->dateTime('payment_confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['garage_id', 'state']);
            $table->index(['garage_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_jobs');
    }
};
