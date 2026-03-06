<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use Illuminate\Http\Request;
use App\Services\ExpenseService;
use App\Services\ExpensePaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpensePaymentRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;
    protected ExpensePaymentService $paymentService;

    public function __construct(
        ExpenseService $expenseService,
        ExpensePaymentService $paymentService
    ) {
        $this->expenseService = $expenseService;
        $this->paymentService = $paymentService;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');

        $this->authorize('viewAny', [Expense::class, $restaurantId]);

        $expenses = Expense::with(['status', 'category', 'supplier'])
            ->where('restaurant_id', $restaurantId)
            ->orderByDesc('expense_date')
            ->paginate(20);

        return response()->json($expenses);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');

        $this->authorize('create', [Expense::class, $restaurantId]);

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_status_id' => 'required|exists:expense_statuses,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'expense_date' => 'required|date',
        ]);

        $validated['restaurant_id'] = $restaurantId;

        $expense = $this->expenseService->create($validated);

        return response()->json($expense, 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_status_id' => 'required|exists:expense_statuses,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'expense_date' => 'required|date',
        ]);

        $updated = $this->expenseService->update($expense, $validated);

        return response()->json($updated);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY (Soft Delete)
    |--------------------------------------------------------------------------
    */
    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $this->expenseService->delete($expense);

        return response()->json([
            'message' => 'Gasto eliminado correctamente.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE PAYMENT
    |--------------------------------------------------------------------------
    */
    public function storePayment(StoreExpensePaymentRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $payment = $this->paymentService->registerPayment(
            $expense,
            $request->validated()
        );

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data' => $payment,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW (with payments + attachments)
    |--------------------------------------------------------------------------
    */
    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        $expense->load(['status', 'category', 'supplier', 'creator',
            'payments.paymentMethod',
            'attachments.uploader',
        ]);

        return response()->json($expense);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST ATTACHMENTS
    |--------------------------------------------------------------------------
    */
    public function listAttachments(Expense $expense)
    {
        $this->authorize('view', $expense);

        $attachments = $expense->attachments()->with('uploader')->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $attachments]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE ATTACHMENT
    |--------------------------------------------------------------------------
    */
    public function storeAttachment(Request $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,gif',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs(
            'expense-attachments/' . $expense->restaurant_id,
            $safeName,
            'public'
        );

        $attachment = ExpenseAttachment::create([
            'expense_id'  => $expense->id,
            'file_path'   => $path,
            'file_name'   => $originalName,
            'uploaded_by' => Auth::id(),
            'created_at'  => now(),
        ]);

        $attachment->load('uploader');

        return response()->json([
            'message' => 'Adjunto subido correctamente.',
            'data'    => $attachment,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY ATTACHMENT
    |--------------------------------------------------------------------------
    */
    public function destroyAttachment(Expense $expense, ExpenseAttachment $attachment)
    {
        $this->authorize('update', $expense);

        if ($attachment->expense_id !== $expense->id) {
            abort(404);
        }

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Adjunto eliminado.']);
    }
}