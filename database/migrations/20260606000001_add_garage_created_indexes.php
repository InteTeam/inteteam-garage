<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mechanics', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'mechanics_garage_id_created_at_index');
        });

        Schema::table('job_stages', function (Blueprint $table) {
            $table->index(['garage_id', 'created_at'], 'job_stages_garage_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('mechanics', function (Blueprint $table) {
            $table->dropIndex('mechanics_garage_id_created_at_index');
        });

        Schema::table('job_stages', function (Blueprint $table) {
            $table->dropIndex('job_stages_garage_id_created_at_index');
        });
    }
};
