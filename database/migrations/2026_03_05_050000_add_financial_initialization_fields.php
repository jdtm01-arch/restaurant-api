<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->timestamp('financial_initialized_at')->nullable()->after('active');
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('financial_account_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained('financial_accounts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn('financial_account_id');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('financial_initialized_at');
        });
    }
};
