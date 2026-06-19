<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_reminders_sent', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('compliance_record_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('channel', 16);
            $table->string('recipient_type', 16);
            $table->string('recipient_id', 64);
            $table->unsignedSmallInteger('window_days');
            // sent_at is the canonical timestamp; mutated only by the error-update path in
            // ComplianceReminderService when CRM dispatch fails. No created_at/updated_at
            // pair — that would duplicate sent_at and pretend the row is generic-mutable.
            $table->timestamp('sent_at');
            $table->text('error')->nullable();

            // Same record + window + recipient never sent twice.
            $table->unique(
                ['compliance_record_id', 'window_days', 'recipient_type', 'recipient_id'],
                'compliance_reminders_dedup_idx',
            );

            // Tenant-scoped list queries (HasGarageScope adds WHERE garage_id = ?).
            $table->index(['garage_id', 'sent_at'], 'idx_compliance_reminders_sent_garage_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reminders_sent');
    }
};
