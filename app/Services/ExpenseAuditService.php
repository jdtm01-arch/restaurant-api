<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseAudit;
use Illuminate\Support\Facades\Auth;

class ExpenseAuditService
{
    public function log(Expense $expense, array $oldValues, array $newValues): void
    {
        foreach ($newValues as $field => $newValue) {

            if (! array_key_exists($field, $oldValues)) {
                continue;
            }

            $oldValue = $oldValues[$field];

            // Solo registrar si realmente cambió
            if ((string) $oldValue !== (string) $newValue) {

                ExpenseAudit::create([
                    'expense_id'    => $expense->id,
                    'changed_by'    => Auth::id(),
                    'field_changed' => $field,
                    'old_value'     => $oldValue,
                    'new_value'     => $newValue,
                    'created_at'    => now(),
                ]);
            }
        }
    }
}