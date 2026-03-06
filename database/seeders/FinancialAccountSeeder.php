<?php

namespace Database\Seeders;

use App\Models\FinancialAccount;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class FinancialAccountSeeder extends Seeder
{
    /**
     * Crea cuentas financieras por defecto para cada restaurante existente.
     */
    public function run(): void
    {
        $defaults = [
            ['name' => 'Caja Física',     'type' => 'cash'],
            ['name' => 'Yape',            'type' => 'digital'],
            ['name' => 'Plin',            'type' => 'digital'],
            ['name' => 'Banco',           'type' => 'bank'],            
        ];

        foreach (Restaurant::all() as $restaurant) {
            foreach ($defaults as $account) {
                FinancialAccount::firstOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'name'          => $account['name'],
                    ],
                    [
                        'type'      => $account['type'],
                        'currency'  => 'PEN',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
