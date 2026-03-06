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
        $restaurantName = env('RESTAURANT_NAME', 'La Conquista');
        $restaurant = Restaurant::create([
            'name'   => $restaurantName,
            'ruc'    => env('RESTAURANT_RUC', '20000000001'),
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
            'email'    => 'super-admin@laconquista.com',
            'password' => Hash::make('AdminRest@2026'),
        ]);
        RestaurantUser::create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $superAdmin->id,
            'role_id'       => $roleGeneral->id,
        ]);

        // Admin de restaurante
        $adminUser = User::create([
            'name'     => 'Administrador de Tienda',
            'email'    => 'admin@laconquista.com',
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
            'email'    => 'mozo@laconquista.com',
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
            'name'           => 'Pollo en Brasa',
            'price_with_tax' => 10.00,
            'is_active'      => true,
        ]);
        $prodGaseosa = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $catBebidas->id,
            'name'           => 'Gaseosa Gordita',
            'price_with_tax' => 4.00,
            'is_active'      => true,
        ]);
        $prodBollo   = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $catComidas->id,
            'name'           => 'Bollo Broaster',
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
         * 7. PROVEEDORES (5)
         * ================================================================ */
        foreach ([
            ['name' => 'Distribuidora El Pollo',    'ruc' => '20100000001', 'phone' => '987654321', 'contact_person' => 'Juan Pérez'],
            ['name' => 'Bebidas y Más SAC',          'ruc' => '20100000002', 'phone' => '912345678', 'contact_person' => 'María López'],
            ['name' => 'Insumos Restaurantes EIRL',  'ruc' => '20100000003', 'phone' => '956789012', 'contact_person' => 'Carlos Díaz'],
            ['name' => 'Proveedora Lima Norte',       'ruc' => '20100000004', 'phone' => '934567890', 'contact_person' => 'Ana Quispe'],
            ['name' => 'Frutas y Verduras Express',  'ruc' => '20100000005', 'phone' => '978901234', 'contact_person' => 'Pedro Huaman'],
        ] as $supplierData) {
            Supplier::create($supplierData);
        }

        /* ================================================================
         * 8. CATEGORÍAS DE GASTOS
         * ================================================================ */
        foreach ([
            'Insumos y Materias Primas',
            'Servicios Básicos',
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

        /* ================================================================
         * 10. INICIALIZACIÓN FINANCIERA (100 soles por cuenta)
         * ================================================================ */
        Auth::loginUsingId($adminUser->id);

        $initService = app(FinancialInitializationService::class);
        $initService->initialize($restaurant->id, [
            ['id' => $cashAccount->id, 'initial_balance' => 100.00, 'description' => 'Saldo inicial apertura'],
            ['id' => $yapeAccount->id, 'initial_balance' => 100.00, 'description' => 'Saldo inicial apertura'],
            ['id' => $plinAccount->id, 'initial_balance' => 100.00, 'description' => 'Saldo inicial apertura'],
            ['id' => $bankAccount->id, 'initial_balance' => 100.00, 'description' => 'Saldo inicial apertura'],
        ], $adminUser->id);

        /* ================================================================
         * OPERACIONES DEL DÍA ANTERIOR
         * ================================================================ */
        $yesterday = Carbon::yesterday();
        Carbon::setTestNow($yesterday);

        /* ── 11. APERTURA DE CAJA ──────────────────────────────────────── */
        $crService    = app(CashRegisterService::class);
        $cashRegister = $crService->open([
            'restaurant_id'  => $restaurant->id,
            'opening_amount' => 100.00,
        ]);

        /* ── 12. PEDIDOS (10) Y VENTAS ───────────────────────────────── */
        $pmEfectivo = PaymentMethod::where('name', 'Efectivo')->first();
        $pmYape     = PaymentMethod::where('name', 'Yape')->first();
        $pmPlin     = PaymentMethod::where('name', 'Plin')->first();
        $pmTarjeta  = PaymentMethod::where('name', 'Tarjeta')->first();

        $saleService = app(SaleService::class);

        /*
         * Estructura: [ [[producto, qty], ...], [[paymentMethod, account, amount], ...] ]
         *
         * Resumen ventas:
         *   Efectivo (Caja) : pedidos 1,4,6,8   → 10+14+20+22     = 66
         *   Yape             : pedidos 2,5,9     → 8+24+16         = 48
         *   Plin             : pedidos 3,7       → 12+12           = 24
         *   Tarjeta (Banco)  : pedido 10         → 26              = 26
         *   Total ventas                                            = 164
         */
        $ordersData = [
            [ [[$prodPollo,   1]],                                       [[$pmEfectivo, $cashAccount, 10.00]] ],
            [ [[$prodGaseosa, 2]],                                       [[$pmYape,     $yapeAccount,  8.00]] ],
            [ [[$prodBollo,   1]],                                       [[$pmPlin,     $plinAccount, 12.00]] ],
            [ [[$prodPollo,   1], [$prodGaseosa, 1]],                    [[$pmEfectivo, $cashAccount, 14.00]] ],
            [ [[$prodBollo,   2]],                                       [[$pmYape,     $yapeAccount, 24.00]] ],
            [ [[$prodPollo,   2]],                                       [[$pmEfectivo, $cashAccount, 20.00]] ],
            [ [[$prodGaseosa, 3]],                                       [[$pmPlin,     $plinAccount, 12.00]] ],
            [ [[$prodPollo,   1], [$prodBollo,   1]],                    [[$pmEfectivo, $cashAccount, 22.00]] ],
            [ [[$prodBollo,   1], [$prodGaseosa, 1]],                    [[$pmYape,     $yapeAccount, 16.00]] ],
            [ [[$prodPollo,   1], [$prodGaseosa, 1], [$prodBollo, 1]],   [[$pmTarjeta,  $bankAccount, 26.00]] ],
        ];

        foreach ($ordersData as $orderDef) {
            [$itemsSpec, $paymentsSpec] = $orderDef;

            $subtotal = collect($itemsSpec)->sum(fn ($i) => $i[0]->price_with_tax * $i[1]);

            $order = Order::create([
                'restaurant_id'       => $restaurant->id,
                'user_id'             => $adminUser->id,
                'channel'             => 'takeaway',
                'status'              => Order::STATUS_CLOSED,
                'subtotal'            => $subtotal,
                'discount_percentage' => 0,
                'discount_amount'     => 0,
                'tax_amount'          => 0,
                'total'               => $subtotal,
                'opened_at'           => now(),
                'closed_at'           => now(),
            ]);

            foreach ($itemsSpec as [$product, $qty]) {
                OrderItem::create([
                    'order_id'                => $order->id,
                    'product_id'              => $product->id,
                    'product_name_snapshot'   => $product->name,
                    'price_with_tax_snapshot' => $product->price_with_tax,
                    'quantity'                => $qty,
                    'subtotal'                => $product->price_with_tax * $qty,
                ]);
            }

            $payments = collect($paymentsSpec)->map(fn ($p) => [
                'payment_method_id'    => $p[0]->id,
                'financial_account_id' => $p[1]->id,
                'amount'               => $p[2],
            ])->toArray();

            $saleService->createFromOrder($order, $payments);
        }

        /* ── 13. GASTOS (2) ──────────────────────────────────────────── */
        $statusPending  = ExpenseStatus::where('slug', 'pending')->first();
        $statusPaid     = ExpenseStatus::where('slug', 'paid')->first();

        $expCatInsumos  = ExpenseCategory::where('restaurant_id', $restaurant->id)
            ->where('name', 'Insumos y Materias Primas')->first();
        $expCatLimpieza = ExpenseCategory::where('restaurant_id', $restaurant->id)
            ->where('name', 'Limpieza y Mantenimiento')->first();

        $expense1 = Expense::create([
            'restaurant_id'       => $restaurant->id,
            'expense_category_id' => $expCatInsumos->id,
            'expense_status_id'   => $statusPending->id,
            'user_id'             => $adminUser->id,
            'amount'              => 30.00,
            'description'         => 'Compra de insumos de cocina',
            'expense_date'        => $yesterday->toDateString(),
        ]);

        $expense2 = Expense::create([
            'restaurant_id'       => $restaurant->id,
            'expense_category_id' => $expCatLimpieza->id,
            'expense_status_id'   => $statusPending->id,
            'user_id'             => $adminUser->id,
            'amount'              => 20.00,
            'description'         => 'Productos de limpieza y mantenimiento',
            'expense_date'        => $yesterday->toDateString(),
        ]);

        /* ── 14. PAGOS DE GASTOS ─────────────────────────────────────── */
        $expPayService = app(ExpensePaymentService::class);

        // Gasto 1: Efectivo → Caja Física
        $expPayService->registerPayment($expense1, [
            'payment_method_id'    => $pmEfectivo->id,
            'financial_account_id' => $cashAccount->id,
            'amount'               => 30.00,
            'paid_at'              => $yesterday->toDateString(),
        ]);
        $expense1->update(['expense_status_id' => $statusPaid->id]);

        // Gasto 2: Yape
        $expPayService->registerPayment($expense2, [
            'payment_method_id'    => $pmYape->id,
            'financial_account_id' => $yapeAccount->id,
            'amount'               => 20.00,
            'paid_at'              => $yesterday->toDateString(),
        ]);
        $expense2->update(['expense_status_id' => $statusPaid->id]);

        /* ── 15. TRANSFERENCIA: Caja Física → Banco (10 soles) ─────────── */
        /*
         * Saldo Caja Física antes de transferencia:
         *   100 (inicial) + 66 (ventas efectivo) − 30 (gasto) = 136
         * La transferencia de 10 soles es válida.
         */
        $transferService = app(AccountTransferService::class);
        $transferService->transfer([
            'restaurant_id'   => $restaurant->id,
            'from_account_id' => $cashAccount->id,
            'to_account_id'   => $bankAccount->id,
            'amount'          => 10.00,
            'description'     => 'Envío de efectivo a cuenta bancaria',
        ]);

        /* ── 16. CIERRE DE CAJA ──────────────────────────────────────── */
        /*
         * Saldo Caja Física al cierre:
         *   100 (inicial) + 66 (ventas) − 30 (gasto) − 10 (transferencia) = 126
         */
        $crService->close($cashRegister, ['closing_amount_real' => 126.00]);

        /* ── 17. CIERRE CONTABLE ─────────────────────────────────────── */
        $closingService = app(CashClosingService::class);
        $closingService->performClosing($restaurant->id, $yesterday->toDateString());

        // ── Limpiar estado global (importante: evitar fugas en tests) ────
        Carbon::setTestNow(null);
        Auth::logout();

        $this->command->info("✔  Seeder completado para restaurante: {$restaurant->name}");
        $this->command->info("   super-admin@laconquista.com  /  AdminRest\@2026");
        $this->command->info("   admin@laconquista.com        /  12345678");
        $this->command->info("   super-mozo@laconquista.com   /  12345678");
    }
}
