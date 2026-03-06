<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('expense_statuses')->insert([
            [
                'name' => 'Pendiente',
                'slug' => 'pending',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pagado',
                'slug' => 'paid',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cancelado',
                'slug' => 'cancelled',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
