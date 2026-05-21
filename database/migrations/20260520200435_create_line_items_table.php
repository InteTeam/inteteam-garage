<?php

use App\Models\LineItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('garage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('estimate_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('price', 10, 2);
            $table->string('status')->default(LineItem::STATUS_PENDING);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['garage_id', 'estimate_id']);
            $table->index(['garage_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
