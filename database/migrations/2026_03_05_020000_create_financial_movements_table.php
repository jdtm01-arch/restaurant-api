<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')
                  ->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')
                  ->constrained('financial_accounts')->restrictOnDelete();
            $table->string('type', 30); // income | expense | transfer_in | transfer_out
            $table->string('reference_type', 50); // sale_payment | expense_payment | transfer | manual_adjustment
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->date('movement_date');
            $table->foreignId('created_by')
                  ->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Índices para cálculo de saldo eficiente
            $table->index(['financial_account_id', 'type']);
            $table->index(['restaurant_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_movements');
    }
};
