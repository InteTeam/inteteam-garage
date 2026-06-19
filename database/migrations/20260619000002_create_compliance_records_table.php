<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16);
            $table->string('source', 16)->default('manual');
            $table->date('expires_on');
            $table->text('note')->nullable();
            $table->timestamps();

            // Latest-per-type lookup for compliance tab
            $table->index(['vehicle_id', 'type', 'created_at']);
            // Tenant-scoped list queries (HasGarageScope adds WHERE garage_id = ?).
            $table->index(['garage_id', 'created_at'], 'idx_compliance_records_garage_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_records');
    }
};
