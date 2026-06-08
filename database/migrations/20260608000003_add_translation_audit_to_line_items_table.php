<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->text('translation_confirmed_text')->nullable()->after('status');
            $table->text('translation_llm_raw')->nullable()->after('translation_confirmed_text');
            $table->foreignUlid('translation_edited_by_mechanic_id')
                ->nullable()
                ->after('translation_llm_raw')
                ->constrained('mechanics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('translation_edited_by_mechanic_id');
            $table->dropColumn(['translation_confirmed_text', 'translation_llm_raw']);
        });
    }
};
