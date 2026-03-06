<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\CashClosingController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\WasteLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinancialMovementController;
use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\FinancialInitializationController;

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('expenses')
    ->group(function () {

        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/{expense}', [ExpenseController::class, 'show']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
        Route::middleware('financial.initialized')
            ->post('/{expense}/payments', [ExpenseController::class, 'storePayment']);
        Route::get('/{expense}/attachments', [ExpenseController::class, 'listAttachments']);
        Route::post('/{expense}/attachments', [ExpenseController::class, 'storeAttachment']);
        Route::delete('/{expense}/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment']);
    });

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->group(function () {
        Route::get('/product-categories', [ProductCategoryController::class, 'index']);
        Route::post('/product-categories', [ProductCategoryController::class, 'store']);
        Route::put('/product-categories/{product_category}', [ProductCategoryController::class, 'update']);
        Route::delete('/product-categories/{product_category}', [ProductCategoryController::class, 'destroy']);

        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::put('/products/{product}/restore', 
            [ProductController::class, 'restore']
        )->name('products.restore');
        Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive']);
    });

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:login')
    ->post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

/*
|--------------------------------------------------------------------------
| Expense Categories Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('expense-categories')
    ->group(function () {

        Route::get('/', [ExpenseCategoryController::class, 'index']);
        Route::post('/', [ExpenseCategoryController::class, 'store']);
        Route::get('/{expense_category}', [ExpenseCategoryController::class, 'show']);
        Route::put('/{expense_category}', [ExpenseCategoryController::class, 'update']);
        Route::delete('/{expense_category}', [ExpenseCategoryController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Payment Methods Module (CRUD)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('payment-methods')
    ->group(function () {

        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::get('/{payment_method}', [PaymentMethodController::class, 'show']);
        Route::put('/{payment_method}', [PaymentMethodController::class, 'update']);
        Route::delete('/{payment_method}', [PaymentMethodController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Suppliers Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('suppliers')
    ->group(function () {

        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::put('/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/{supplier}', [SupplierController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Tables Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('tables')
    ->group(function () {

        Route::get('/', [TableController::class, 'index']);
        Route::post('/', [TableController::class, 'store']);
        Route::get('/{table}', [TableController::class, 'show']);
        Route::put('/{table}', [TableController::class, 'update']);
        Route::delete('/{table}', [TableController::class, 'destroy']);
        Route::put('/{table}/restore', [TableController::class, 'restore'])
            ->name('tables.restore');
        Route::post('/positions', [TableController::class, 'updatePositions']);

    });

/*
|--------------------------------------------------------------------------
| Cash Registers Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('cash-registers')
    ->group(function () {

        Route::get('/', [CashRegisterController::class, 'index']);
        Route::get('/current', [CashRegisterController::class, 'current']);
        Route::get('/{cashRegister}', [CashRegisterController::class, 'show']);
        Route::get('/{cashRegister}/x-report', [CashRegisterController::class, 'xReport']);

        // Write operations require financial initialization
        Route::middleware('financial.initialized')->group(function () {
            Route::post('/', [CashRegisterController::class, 'open']);
            Route::post('/{cashRegister}/close', [CashRegisterController::class, 'close']);
        });

    });

/*
|--------------------------------------------------------------------------
| Orders Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('orders')
    ->group(function () {

        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);

        // Items
        Route::post('/{order}/items', [OrderController::class, 'addItem']);
        Route::delete('/{order}/items/{item}', [OrderController::class, 'removeItem']);
        Route::patch('/{order}/items/{item}/quantity', [OrderController::class, 'updateItemQuantity']);

        // Actions
        Route::post('/{order}/discount', [OrderController::class, 'applyDiscount']);
        Route::post('/{order}/close', [OrderController::class, 'close']);
        Route::post('/{order}/reopen', [OrderController::class, 'reopen']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{order}/kitchen-ticket', [OrderController::class, 'kitchenTicket']);
        Route::get('/{order}/bill', [OrderController::class, 'bill']);
        Route::patch('/{order}/change-table', [OrderController::class, 'changeTable']);

        // Pay — requires financial initialization
        Route::middleware('financial.initialized')
            ->post('/{order}/pay', [OrderController::class, 'pay']);

    });

/*
|--------------------------------------------------------------------------
| Sales Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('sales')
    ->group(function () {

        Route::get('/', [SaleController::class, 'index']);
        Route::get('/summary', [SaleController::class, 'summary']);
        Route::get('/{sale}', [SaleController::class, 'show']);
        Route::get('/{sale}/receipt', [SaleController::class, 'receipt']);

    });

/*
|--------------------------------------------------------------------------
| Cash Closings Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('cash-closings')
    ->group(function () {

        Route::get('/', [CashClosingController::class, 'index']);
        Route::get('/preview', [CashClosingController::class, 'preview']);
        Route::post('/', [CashClosingController::class, 'store']);
        Route::get('/{cashClosing}', [CashClosingController::class, 'show']);

    });

/*
|--------------------------------------------------------------------------
| Audit Logs
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->get('/audit-logs', [AuditController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Waste Logs Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('waste-logs')
    ->group(function () {

        Route::get('/', [WasteLogController::class, 'index']);
        Route::post('/', [WasteLogController::class, 'store']);
        Route::get('/{wasteLog}', [WasteLogController::class, 'show']);
        Route::put('/{wasteLog}', [WasteLogController::class, 'update']);
        Route::delete('/{wasteLog}', [WasteLogController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->get('/dashboard', [DashboardController::class, 'index']);

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->get('/dashboard/waiter', [DashboardController::class, 'waiter']);

/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant', 'throttle:reports'])
    ->prefix('reports')
    ->group(function () {

        Route::get('/sales-by-category', [ReportController::class, 'salesByCategory']);
        Route::get('/sales-by-hour', [ReportController::class, 'salesByHour']);
        Route::get('/cancellations-discounts', [ReportController::class, 'cancellationsAndDiscounts']);
        Route::get('/sales-by-waiter', [ReportController::class, 'salesByWaiter']);
        Route::get('/food-cost', [ReportController::class, 'foodCost']);
        Route::get('/waste', [ReportController::class, 'waste']);
        Route::get('/accounts-payable', [ReportController::class, 'accountsPayable']);
        Route::get('/daily-cash-flow', [ReportController::class, 'dailyCashFlow']);
        Route::get('/top-products', [ReportController::class, 'topProducts']);
        Route::get('/daily-summary', [ReportController::class, 'dailySummary']);

    });

/*
|--------------------------------------------------------------------------
| Catalogs (global, no restaurant context needed)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('catalogs')
    ->group(function () {

        Route::get('/roles', [CatalogController::class, 'roles']);
        Route::get('/payment-methods', [CatalogController::class, 'paymentMethods']);
        Route::get('/expense-statuses', [CatalogController::class, 'expenseStatuses']);

    });

/*
|--------------------------------------------------------------------------
| Users Module (per restaurant)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('users')
    ->group(function () {

        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);

    });

/*
|--------------------------------------------------------------------------
| Financial Accounts Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('financial-accounts')
    ->group(function () {

        Route::get('/', [FinancialAccountController::class, 'index']);
        Route::post('/', [FinancialAccountController::class, 'store']);
        Route::get('/balances', [FinancialAccountController::class, 'balances']);
        Route::get('/{financialAccount}', [FinancialAccountController::class, 'show']);
        Route::put('/{financialAccount}', [FinancialAccountController::class, 'update']);
        Route::delete('/{financialAccount}', [FinancialAccountController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Financial Movements Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->get('/financial-movements', [FinancialMovementController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Account Transfers Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('account-transfers')
    ->group(function () {

        Route::get('/', [AccountTransferController::class, 'index']);

        // Write operations require financial initialization
        Route::middleware('financial.initialized')->group(function () {
            Route::post('/', [AccountTransferController::class, 'store']);
            Route::put('/{accountTransfer}', [AccountTransferController::class, 'update']);
        });

        Route::delete('/{accountTransfer}', [AccountTransferController::class, 'destroy']);

    });

/*
|--------------------------------------------------------------------------
| Financial Initialization Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'set.restaurant'])
    ->prefix('financial')
    ->group(function () {

        Route::get('/status', [FinancialInitializationController::class, 'status']);
        Route::post('/initialize', [FinancialInitializationController::class, 'initialize']);

    });