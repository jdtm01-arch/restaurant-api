<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('unidad');
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->date('waste_date');
            $table->string('reason', 100)->nullable();
            $table->timestamps();

            $table->index('restaurant_id');
            $table->index('waste_date');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_logs');
    }
};
