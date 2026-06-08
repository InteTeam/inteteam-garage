<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_stages', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('name');
            $table->text('notes_translated')->nullable()->after('notes');
            $table->string('notes_source_locale', 5)->nullable()->after('notes_translated');
            $table->string('notes_target_locale', 5)->nullable()->after('notes_source_locale');
            $table->timestamp('notes_translated_at')->nullable()->after('notes_target_locale');
        });
    }

    public function down(): void
    {
        Schema::table('job_stages', function (Blueprint $table) {
            $table->dropColumn([
                'notes',
                'notes_translated',
                'notes_source_locale',
                'notes_target_locale',
                'notes_translated_at',
            ]);
        });
    }
};
