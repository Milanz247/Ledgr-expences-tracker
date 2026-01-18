<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Expense;
use App\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $loans = $request->user()->loans()
            ->with(['expenses.category'])
            ->latest()
            ->get()
            ->map(function ($loan) {
                $loan->expenses = $loan->expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                        'date' => $expense->date,
                        'category' => [
                            'name' => $expense->category->name,
                            'icon' => $expense->category->icon,
                        ],
                    ];
                });
                return $loan;
            });

        return response()->json($loans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lender_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_funding_source' => 'nullable|boolean',
        ]);

        $loan = $request->user()->loans()->create([
            'lender_name' => $request->lender_name,
            'amount' => $request->amount,
            'balance_remaining' => $request->amount,
            'description' => $request->description,
            'status' => 'unpaid',
            'due_date' => $request->due_date,
            'is_funding_source' => $request->is_funding_source ?? false,
        ]);

        return response()->json($loan, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Loan $loan)
    {
        if ($loan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($loan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Loan $loan)
    {
        if ($loan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'lender_name' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $loan->update($request->only(['lender_name', 'amount', 'description', 'due_date']));

        return response()->json($loan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Loan $loan)
    {
        if ($loan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($loan->expenses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete. This loan has transaction history.'
            ], 422);
        }

        $loan->delete();

        return response()->json(['message' => 'Loan deleted successfully']);
    }

    /**
     * Make a repayment on a loan.
     */
    public function repay(Request $request, Loan $loan)
    {
        if ($loan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($request->amount > $loan->balance_remaining) {
            return response()->json([
                'message' => 'Repayment amount cannot exceed remaining balance.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $expense = Expense::create([
                'user_id' => $request->user()->id,
                'category_id' => $request->category_id,
                'bank_account_id' => $request->bank_account_id,
                'fund_source_id' => $request->fund_source_id,
                'loan_id' => $loan->id,
                'amount' => $request->amount,
                'description' => $request->description ?? "Repayment to {$loan->lender_name}",
                'date' => $request->date,
            ]);

            if ($request->bank_account_id) {
                $bankAccount = $request->user()->bankAccounts()->find($request->bank_account_id);
                if ($bankAccount) {
                    $bankAccount->balance -= $request->amount;
                    $bankAccount->save();
                }
            }

            if ($request->fund_source_id) {
                $fundSource = $request->user()->fundSources()->find($request->fund_source_id);
                if ($fundSource) {
                    $fundSource->amount -= $request->amount;
                    $fundSource->save();
                }
            }

            $loan->balance_remaining -= $request->amount;

            if ($loan->balance_remaining <= 0) {
                $loan->status = 'paid';
                $loan->balance_remaining = 0;
            } else if ($loan->balance_remaining < $loan->amount) {
                $loan->status = 'partially_paid';
            }

            $loan->save();

            // Create repayment record for tracking
            Repayment::create([
                'user_id' => $request->user()->id,
                'loan_id' => $loan->id,
                'expense_id' => $expense->id,
                'amount' => $request->amount,
                'payment_date' => $request->date,
                'description' => $request->description,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Repayment recorded successfully',
                'loan' => $loan,
                'expense' => $expense,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process repayment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get loans statistics
     */
    public function stats(Request $request)
    {
        $totalDebt = $request->user()->loans()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->sum('balance_remaining');

        $paidLoans = $request->user()->loans()->where('status', 'paid')->count();
        $activeLoans = $request->user()->loans()->whereIn('status', ['unpaid', 'partially_paid'])->count();

        return response()->json([
            'total_debt' => $totalDebt,
            'paid_loans' => $paidLoans,
            'active_loans' => $activeLoans,
        ]);
    }

    /**
     * Get repayment history for a loan
     */
    public function repayments(Request $request, Loan $loan)
    {
        if ($loan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $repayments = $loan->repayments()
            ->with('expense.category')
            ->orderBy('payment_date', 'desc')
            ->get();

        return response()->json($repayments);
    }
}

