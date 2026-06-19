<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->boolean('compliance_reminders_enabled')->default(false)->after('timeout_reminder_policy');
            $table->string('compliance_reminders_channel', 16)->nullable()->after('compliance_reminders_enabled');
            $table->json('compliance_reminders_windows')->nullable()->after('compliance_reminders_channel');
            $table->string('compliance_reminders_recipient', 32)->default('customer')->after('compliance_reminders_windows');
            $table->json('compliance_reminders_types')->nullable()->after('compliance_reminders_recipient');
        });
    }

    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropColumn([
                'compliance_reminders_enabled',
                'compliance_reminders_channel',
                'compliance_reminders_windows',
                'compliance_reminders_recipient',
                'compliance_reminders_types',
            ]);
        });
    }
};
