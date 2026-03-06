<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Sale;
use App\Models\CashClosing;
use App\Models\AuditLog;
use App\Models\WasteLog;
use App\Models\FinancialAccount;
use App\Models\AccountTransfer;
use App\Models\FinancialMovement;
use App\Policies\ExpensePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TablePolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\OrderPolicy;
use App\Policies\SalePolicy;
use App\Policies\CashClosingPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\WasteLogPolicy;
use App\Policies\FinancialAccountPolicy;
use App\Policies\AccountTransferPolicy;
use App\Policies\FinancialMovementPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Expense::class => ExpensePolicy::class,
        Product::class => ProductPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        Supplier::class => SupplierPolicy::class,
        Table::class => TablePolicy::class,
        CashRegister::class => CashRegisterPolicy::class,
        Order::class => OrderPolicy::class,
        Sale::class => SalePolicy::class,
        CashClosing::class => CashClosingPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        WasteLog::class => WasteLogPolicy::class,
        FinancialAccount::class => FinancialAccountPolicy::class,
        AccountTransfer::class => AccountTransferPolicy::class,
        FinancialMovement::class => FinancialMovementPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}