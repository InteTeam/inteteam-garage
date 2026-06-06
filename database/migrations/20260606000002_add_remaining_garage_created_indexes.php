<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'vehicles_garage_id_created_at_index');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'estimates_garage_id_created_at_index');
        });

        Schema::table('line_items', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'line_items_garage_id_created_at_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'media_garage_id_created_at_index');
        });

        Schema::table('signed_portal_tokens', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'signed_portal_tokens_garage_id_created_at_index');
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'notification_preferences_garage_id_created_at_index');
            $table->index(['garage_id', 'channel'], 'notification_preferences_garage_id_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropIndex('notification_preferences_garage_id_channel_index');
            $table->dropIndex('notification_preferences_garage_id_created_at_index');
        });

        Schema::table('signed_portal_tokens', function (Blueprint $table) {
            $table->dropIndex('signed_portal_tokens_garage_id_created_at_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_garage_id_created_at_index');
        });

        Schema::table('line_items', function (Blueprint $table) {
            $table->dropIndex('line_items_garage_id_created_at_index');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex('estimates_garage_id_created_at_index');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_garage_id_created_at_index');
        });
    }
};
