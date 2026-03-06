<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('date');

            $table->foreignId('opened_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('opening_amount', 12, 2);
            $table->decimal('closing_amount_expected', 12, 2)->nullable();
            $table->decimal('closing_amount_real', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['restaurant_id', 'date']);
            $table->index('restaurant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
