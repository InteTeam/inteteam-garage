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
            $table->boolean('staff_channel_toggle_default')->default(true)->after('default_notification_channel');
            $table->string('timeout_reminder_policy', 32)->default('24_7')->after('staff_channel_toggle_default');
            $table->json('working_hours')->nullable()->after('timeout_reminder_policy');
        });
    }

    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropColumn(['staff_channel_toggle_default', 'timeout_reminder_policy', 'working_hours']);
        });
    }
};
