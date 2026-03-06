<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->constrained('users');

            $table->date('date'); // fecha del cierre
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);

            $table->timestamp('closed_at');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'date']); // 1 cierre por día por restaurante
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};