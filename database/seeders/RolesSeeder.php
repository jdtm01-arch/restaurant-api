<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'name' => 'Administrador General',
                'slug' => 'admin_general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Administrador Restaurante',
                'slug' => 'admin_restaurante',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Caja',
                'slug' => 'caja',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mozo',
                'slug' => 'mozo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cocina',
                'slug' => 'cocina',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
