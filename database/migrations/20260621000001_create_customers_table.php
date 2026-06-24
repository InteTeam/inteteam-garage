<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('crm_customer_id')->nullable()->index();
            $table->string('email')->unique();
            $table->string('name');
            $table->timestamp('last_login_at')->nullable();
            // Required by Illuminate\Auth\Authenticatable trait — used when
            // Auth::login(..., remember: true) issues the persistent cookie.
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
