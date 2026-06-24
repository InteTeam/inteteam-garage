<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// sessions.user_id was bigint unsigned (Laravel default for the mechanic
// User table). The customer guard logs in App\Models\Customer which has a
// 26-char ULID primary key, so the database session driver tried to write
// "01kvx..." into a bigint and crashed with "Data truncated". Widen to a
// string column wide enough for either an int or a ULID. The mechanic side
// still writes the integer id (auto-cast to string on the way in).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->string('user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
