<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseStatus;
use App\Models\Restaurant;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_marcar_como_paid_sin_pagos()
    {
        // Ejecutar migraciones
        $this->seed();

        // 1️⃣ Crear restaurante
        $restaurant = Restaurant::create([
            'name' => 'Restaurante Test',
            'ruc' => '12345678901',
        ]);

        // 2️⃣ Obtener o crear rol admin_restaurante
        $role = Role::where('slug', 'admin_restaurante')->first();

        if (! $role) {
            $role = Role::create([
                'name' => 'Admin Restaurante',
                'slug' => 'admin_restaurante',
            ]);
        }

        // 3️⃣ Crear usuario
        $user = User::factory()->create();

        // 4️⃣ Asociar usuario al restaurante con rol
        DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // 5️⃣ Obtener estados
        $pendingStatus = ExpenseStatus::where('slug', 'pending')->first();
        $paidStatus = ExpenseStatus::where('slug', 'paid')->first();

        // Crear categoría válida para el restaurante
        $category = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Categoria Test',
        ]);

        // 6️⃣ Crear gasto en estado pending
        $expense = Expense::create([
            'restaurant_id' => $restaurant->id,
            'expense_category_id' => $category->id,
            'expense_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'amount' => 100,
            'description' => 'Test expense',
            'expense_date' => now()->toDateString(),
        ]);

        // 7️⃣ Intentar marcar como paid SIN pagos
        $response = $this
            ->withHeader('X-Restaurant-Id', $restaurant->id)
            ->putJson("/api/expenses/{$expense->id}", [
                'restaurant_id' => $restaurant->id,
                'expense_category_id' => $category->id,
                'expense_status_id' => $paidStatus->id,
                'user_id' => $user->id,
                'amount' => 100,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date,
            ]);

        // 8️⃣ Debe fallar
       $response->assertStatus(422);// luego ajustamos si es 422
    }

    public function test_permite_marcar_como_paid_con_suma_exacta()
    {
        $this->seed();

        // 1️⃣ Crear restaurante
        $restaurant = \App\Models\Restaurant::create([
            'name' => 'Restaurante Test 2',
            'ruc' => '99999999999',
        ]);

        // 2️⃣ Rol
        $role = \App\Models\Role::where('slug', 'admin_restaurante')->first();

        if (! $role) {
            $role = \App\Models\Role::create([
                'name' => 'Admin Restaurante',
                'slug' => 'admin_restaurante',
            ]);
        }

        // 3️⃣ Usuario
        $user = \App\Models\User::factory()->create();

        \Illuminate\Support\Facades\DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // 4️⃣ Estados
        $pendingStatus = \App\Models\ExpenseStatus::where('slug', 'pending')->first();
        $paidStatus = \App\Models\ExpenseStatus::where('slug', 'paid')->first();

        // 5️⃣ Categoría
        $category = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Categoria Test 2',
        ]);

        // 6️⃣ Crear gasto
        $expense = \App\Models\Expense::create([
            'restaurant_id' => $restaurant->id,
            'expense_category_id' => $category->id,
            'expense_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'amount' => 100,
            'description' => 'Expense Test 2',
            'expense_date' => now()->toDateString(),
        ]);

        // 7️⃣ Crear pago EXACTO (100)
        \App\Models\ExpensePayment::create([
            'expense_id' => $expense->id,
            'payment_method_id' => 1,
            'amount' => 100,
            'paid_at' => now(),
        ]);

        // 8️⃣ Intentar marcar como paid
        $response = $this
            ->withHeader('X-Restaurant-Id', $restaurant->id)
            ->putJson("/api/expenses/{$expense->id}", [
                'restaurant_id' => $restaurant->id,
                'expense_category_id' => $category->id,
                'expense_status_id' => $paidStatus->id,
                'user_id' => $user->id,
                'amount' => 100,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date,
            ]);

        $response->dump();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'expense_status_id' => $paidStatus->id,
        ]);
    }

    public function test_no_permite_marcar_como_paid_con_suma_menor()
    {
        $this->seed();

        // 1️⃣ Restaurante
        $restaurant = \App\Models\Restaurant::create([
            'name' => 'Restaurante Test 3',
            'ruc' => '88888888888',
        ]);

        // 2️⃣ Rol
        $role = \App\Models\Role::where('slug', 'admin_restaurante')->first();

        if (! $role) {
            $role = \App\Models\Role::create([
                'name' => 'Admin Restaurante',
                'slug' => 'admin_restaurante',
            ]);
        }

        // 3️⃣ Usuario
        $user = \App\Models\User::factory()->create();

        \Illuminate\Support\Facades\DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // 4️⃣ Estados
        $pendingStatus = \App\Models\ExpenseStatus::where('slug', 'pending')->first();
        $paidStatus = \App\Models\ExpenseStatus::where('slug', 'paid')->first();

        // 5️⃣ Categoría
        $category = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Categoria Test 3',
        ]);

        // 6️⃣ Gasto de 100
        $expense = \App\Models\Expense::create([
            'restaurant_id' => $restaurant->id,
            'expense_category_id' => $category->id,
            'expense_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'amount' => 100,
            'description' => 'Expense Test 3',
            'expense_date' => now()->toDateString(),
        ]);

        // 7️⃣ Pago MENOR (50)
        \App\Models\ExpensePayment::create([
            'expense_id' => $expense->id,
            'payment_method_id' => 1,
            'amount' => 50,
            'paid_at' => now(),
        ]);

        // 8️⃣ Intentar marcar como paid
        $response = $this
            ->withHeader('X-Restaurant-Id', $restaurant->id)
            ->putJson("/api/expenses/{$expense->id}", [
                'restaurant_id' => $restaurant->id,
                'expense_category_id' => $category->id,
                'expense_status_id' => $paidStatus->id,
                'user_id' => $user->id,
                'amount' => 100,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'expense_status_id' => $pendingStatus->id,
        ]);
    }

    public function test_no_permite_marcar_como_paid_con_suma_mayor()
    {
        $this->seed();

        // 1️⃣ Restaurante
        $restaurant = \App\Models\Restaurant::create([
            'name' => 'Restaurante Test 4',
            'ruc' => '77777777777',
        ]);

        // 2️⃣ Rol
        $role = \App\Models\Role::where('slug', 'admin_restaurante')->first();

        if (! $role) {
            $role = \App\Models\Role::create([
                'name' => 'Admin Restaurante',
                'slug' => 'admin_restaurante',
            ]);
        }

        // 3️⃣ Usuario
        $user = \App\Models\User::factory()->create();

        \Illuminate\Support\Facades\DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // 4️⃣ Estados
        $pendingStatus = \App\Models\ExpenseStatus::where('slug', 'pending')->first();
        $paidStatus = \App\Models\ExpenseStatus::where('slug', 'paid')->first();

        // 5️⃣ Categoría
        $category = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Categoria Test 4',
        ]);

        // 6️⃣ Gasto de 100
        $expense = \App\Models\Expense::create([
            'restaurant_id' => $restaurant->id,
            'expense_category_id' => $category->id,
            'expense_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'amount' => 100,
            'description' => 'Expense Test 4',
            'expense_date' => now()->toDateString(),
        ]);

        // 7️⃣ Pago MAYOR (150)
        \App\Models\ExpensePayment::create([
            'expense_id' => $expense->id,
            'payment_method_id' => 1,
            'amount' => 150,
            'paid_at' => now(),
        ]);

        // 8️⃣ Intentar marcar como paid
        $response = $this
            ->withHeader('X-Restaurant-Id', $restaurant->id)
            ->putJson("/api/expenses/{$expense->id}", [
                'restaurant_id' => $restaurant->id,
                'expense_category_id' => $category->id,
                'expense_status_id' => $paidStatus->id,
                'user_id' => $user->id,
                'amount' => 100,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'expense_status_id' => $pendingStatus->id,
        ]);
    }

    public function test_setea_paid_at_cuando_se_marca_como_paid()
    {
        $this->seed();

        // 1️⃣ Restaurante
        $restaurant = \App\Models\Restaurant::create([
            'name' => 'Restaurante Test 5',
            'ruc' => '66666666666',
        ]);

        // 2️⃣ Rol
        $role = \App\Models\Role::where('slug', 'admin_restaurante')->first();

        if (! $role) {
            $role = \App\Models\Role::create([
                'name' => 'Admin Restaurante',
                'slug' => 'admin_restaurante',
            ]);
        }

        // 3️⃣ Usuario
        $user = \App\Models\User::factory()->create();

        \Illuminate\Support\Facades\DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // 4️⃣ Estados
        $pendingStatus = \App\Models\ExpenseStatus::where('slug', 'pending')->first();
        $paidStatus = \App\Models\ExpenseStatus::where('slug', 'paid')->first();

        // 5️⃣ Categoría
        $category = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Categoria Test 5',
        ]);

        // 6️⃣ Gasto
        $expense = \App\Models\Expense::create([
            'restaurant_id' => $restaurant->id,
            'expense_category_id' => $category->id,
            'expense_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'amount' => 200,
            'description' => 'Expense Test 5',
            'expense_date' => now()->toDateString(),
        ]);

        // 7️⃣ Pago exacto
        \App\Models\ExpensePayment::create([
            'expense_id' => $expense->id,
            'payment_method_id' => 1,
            'amount' => 200,
            'paid_at' => now(),
        ]);

        // 8️⃣ Marcar como paid
        $response = $this
            ->withHeader('X-Restaurant-Id', $restaurant->id)
            ->putJson("/api/expenses/{$expense->id}", [
                'restaurant_id' => $restaurant->id,
                'expense_category_id' => $category->id,
                'expense_status_id' => $paidStatus->id,
                'user_id' => $user->id,
                'amount' => 200,
                'description' => $expense->description,
                'expense_date' => $expense->expense_date,
            ]);

        $response->assertStatus(200);

        $expense->refresh();

        $this->assertNotNull($expense->paid_at);
    }
}