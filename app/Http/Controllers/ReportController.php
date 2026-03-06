<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * 1. Ventas por categoría y canal
     * GET /reports/sales-by-category?date_from=&date_to=&channel=
     */
    public function salesByCategory(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->salesByCategory(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
            $request->input('channel'),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 2. Ventas por horario
     * GET /reports/sales-by-hour?date_from=&date_to=
     */
    public function salesByHour(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->salesByHour(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 3. Anulaciones, cancelaciones y descuentos
     * GET /reports/cancellations-discounts?date_from=&date_to=
     */
    public function cancellationsAndDiscounts(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->cancellationsAndDiscounts(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 4. Ventas por mesero
     * GET /reports/sales-by-waiter?date_from=&date_to=
     */
    public function salesByWaiter(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->salesByWaiter(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 5. Food cost
     * GET /reports/food-cost?date_from=&date_to=
     */
    public function foodCost(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->foodCost(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 6. Mermas y desperdicios
     * GET /reports/waste?date_from=&date_to=
     */
    public function waste(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->waste(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 7. Cuentas por pagar
     * GET /reports/accounts-payable
     */
    public function accountsPayable(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        $data = $this->reportService->accountsPayable(
            $request->get('restaurant_id'),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 8. Flujo de efectivo diario
     * GET /reports/daily-cash-flow?date_from=&date_to=
     */
    public function dailyCashFlow(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->dailyCashFlow(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 9. Productos más/menos vendidos
     * GET /reports/top-products?date_from=&date_to=&limit=10
     */
    public function topProducts(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $params = $this->validateDateRange($request);

        $data = $this->reportService->topProducts(
            $request->get('restaurant_id'),
            $params['date_from'],
            $params['date_to'],
            (int) $request->input('limit', 10),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 10. Resumen ejecutivo del día
     * GET /reports/daily-summary?date=
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $data = $this->reportService->dailySummary(
            $request->get('restaurant_id'),
            $validated['date'],
        );

        return response()->json(['data' => $data]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Inline authorization — only admin_general and admin_restaurante roles.
     */
    private function authorizeReport(Request $request): void
    {
        $user = $request->user();
        $restaurantId = $request->get('restaurant_id');

        $role = $user->roleForRestaurant($restaurantId);

        if (! $role || ! in_array($role->slug, ['admin_general', 'admin_restaurante'])) {
            abort(403, 'No tienes permiso para acceder a reportes.');
        }
    }

    /**
     * Validate and return common date_from / date_to parameters.
     */
    private function validateDateRange(Request $request): array
    {
        return $request->validate([
            'date_from' => ['required', 'date', 'before_or_equal:today'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:today'],
        ]);
    }
}
