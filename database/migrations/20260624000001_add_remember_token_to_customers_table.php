<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The initial customers migration shipped with $table->rememberToken() in the
// source, but a dev DB had already run an earlier revision of the file without
// it. Auth::login(remember: true) then 500'd on the missing column. This
// migration patches existing environments; a fresh `migrate:fresh` is a no-op
// here because the original migration already creates the column.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'remember_token')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->rememberToken();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'remember_token')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropColumn('remember_token');
            });
        }
    }
};
