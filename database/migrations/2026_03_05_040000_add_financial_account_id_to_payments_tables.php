<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega financial_account_id a sale_payments y expense_payments.
     * Nullable para no romper datos históricos existentes.
     */
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')
                  ->nullable()
                  ->after('payment_method_id')
                  ->constrained('financial_accounts')
                  ->restrictOnDelete();
        });

        Schema::table('expense_payments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')
                  ->nullable()
                  ->after('payment_method_id')
                  ->constrained('financial_accounts')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_account_id');
        });

        Schema::table('expense_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_account_id');
        });
    }
};
