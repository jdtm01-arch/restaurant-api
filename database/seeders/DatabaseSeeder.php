<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatus;
use App\Models\FinancialAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\User;
use App\Services\AccountTransferService;
use App\Services\CashClosingService;
use App\Services\CashRegisterService;
use App\Services\ExpensePaymentService;
use App\Services\FinancialInitializationService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /* ================================================================
         * 1. CATÁLOGOS BASE
         * ================================================================ */
        $this->call([
            RolesSeeder::class,
            ExpenseStatusSeeder::class,
            PaymentMethodSeeder::class,
        ]);

        /* ================================================================
         * 2. RESTAURANTE
         * ================================================================ */
        $restaurantName = env('RESTAURANT_NAME', 'TU RESTAURANTE');
        $restaurant = Restaurant::create([
            'name'   => $restaurantName,
            'ruc'    => env('RESTAURANT_RUC', '-'),
            'active' => true,
        ]);

        /* ================================================================
         * 3. USUARIOS
         * ================================================================ */
        $roleGeneral    = Role::where('slug', 'admin_general')->first();
        $roleRestaurant = Role::where('slug', 'admin_restaurante')->first();
        $roleMozo       = Role::where('slug', 'mozo')->first();

        // Super-admin (admin_general)
        $superAdmin = User::create([
            'name'     => 'Super Administrador',
            'email'    => 'super-admin@TURESTAURANTE.com',
            'password' => Hash::make('AdminRest@2026'),
        ]);
        RestaurantUser::create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $superAdmin->id,
            'role_id'       => $roleGeneral->id,
        ]);

        // Admin de restaurante
        $adminUser = User::create([
            'name'     => 'Administrador',
            'email'    => 'admin@TURESTAURANTE.com',
            'password' => Hash::make('12345678'),
        ]);
        RestaurantUser::create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $adminUser->id,
            'role_id'       => $roleRestaurant->id,
        ]);

        // Mozo de prueba
        $mozo = User::create([
            'name'     => 'Mozo Principal',
            'email'    => 'mozo@TURESTAURANTE.com',
            'password' => Hash::make('12345678'),
        ]);
        RestaurantUser::create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $mozo->id,
            'role_id'       => $roleMozo->id,
        ]);

        /* ================================================================
         * 4. CATEGORÍAS DE PRODUCTOS
         * ================================================================ */
        $catComidas    = ProductCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Comidas']);
        $catBebidas    = ProductCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Bebidas']);
        ProductCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Cocteles']);
        ProductCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Aperitivos']);

        /* ================================================================
         * 5. PRODUCTOS
         * ================================================================ */
        $prodPollo   = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $catComidas->id,
            'name'           => '1/4 Pollo a la Brasa',
            'price_with_tax' => 10.00,
            'is_active'      => true,
        ]);
        $prodGaseosa = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $catBebidas->id,
            'name'           => 'Gaseosa InkaCola Gordita',
            'price_with_tax' => 4.00,
            'is_active'      => true,
        ]);
        $prodBollo   = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $catComidas->id,
            'name'           => '1/4 Pollo Broaster',
            'price_with_tax' => 12.00,
            'is_active'      => true,
        ]);

        /* ================================================================
         * 6. MESAS (8)
         * ================================================================ */
        for ($i = 1; $i <= 8; $i++) {
            Table::create([
                'restaurant_id' => $restaurant->id,
                'number'        => $i,
                'name'          => "Mesa {$i}",
                'is_active'     => true,
            ]);
        }

        /* ================================================================
         * 8. CATEGORÍAS DE GASTOS
         * ================================================================ */
        foreach ([
            'Insumos y Materias Primas',
            'Servicios',
            'Planilla y Personal',
            'Mantenimiento',
            'Marketing y Publicidad',
            'Limpieza y Mantenimiento',
        ] as $catName) {
            ExpenseCategory::create([
                'restaurant_id' => $restaurant->id,
                'name'          => $catName,
                'active'        => true,
            ]);
        }

        /* ================================================================
         * 9. CUENTAS FINANCIERAS
         * ================================================================ */
        $this->call(FinancialAccountSeeder::class);

        $accounts    = FinancialAccount::where('restaurant_id', $restaurant->id)->get()->keyBy('name');
        $cashAccount = $accounts['Caja Física'];
        $yapeAccount = $accounts['Yape'];
        $plinAccount = $accounts['Plin'];
        $bankAccount = $accounts['Banco'];       

        $this->command->info("✔  Seeder completado para restaurante: {$restaurant->name}");
        $this->command->info("   super-admin@TURESTAURANTE.com  /  AdminRest\@2026");
        $this->command->info("   admin@TURESTAURANTE.com        /  12345678");
        $this->command->info("   super-mozo@TURESTAURANTE.com   /  12345678");
    }
}
