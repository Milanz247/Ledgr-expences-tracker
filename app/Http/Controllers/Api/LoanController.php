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

        // Store original values BEFORE any modifications
        $originalAmount = $loan->amount;
        $originalBalance = $loan->balance_remaining;

        \Log::info('Loan Update - Before', [
            'loan_id' => $loan->id,
            'original_amount' => $originalAmount,
            'original_balance' => $originalBalance,
            'request_amount' => $request->amount,
        ]);

        // Update basic fields 
        $loan->lender_name = $request->lender_name ?? $loan->lender_name;
        $loan->description = $request->description ?? $loan->description;
        $loan->due_date = $request->due_date ?? $loan->due_date;

        // If amount is being updated, recalculate balance_remaining
        if ($request->has('amount') && $request->amount != $originalAmount) {
            $newAmount = $request->amount;
            
            // Check if current data is corrupted (balance > amount)
            if ($originalBalance > $originalAmount) {
                \Log::warning('Loan data corrupted - balance greater than amount', [
                    'loan_id' => $loan->id,
                    'amount' => $originalAmount,
                    'balance' => $originalBalance,
                ]);
                // If data is corrupted, reset balance to new amount (assume unpaid)
                $loan->amount = $newAmount;
                $loan->balance_remaining = $newAmount;
                $loan->status = 'unpaid';
            } else {
                // Calculate how much has already been paid
                $paidAmount = $originalAmount - $originalBalance;
                
                \Log::info('Loan Update - Calculation', [
                    'old_amount' => $originalAmount,
                    'new_amount' => $newAmount,
                    'old_balance' => $originalBalance,
                    'paid_amount' => $paidAmount,
                ]);
                
                // Calculate new balance remaining
                $newBalanceRemaining = $newAmount - $paidAmount;
                
                \Log::info('Loan Update - New Balance', [
                    'calculated_balance' => $newBalanceRemaining,
                    'after_max' => max(0, $newBalanceRemaining),
                ]);
                
                // Update amount and balance
                $loan->amount = $newAmount;
                $loan->balance_remaining = max(0, $newBalanceRemaining);
                
                // Update status based on new balance
                if ($loan->balance_remaining <= 0) {
                    $loan->status = 'paid';
                    $loan->balance_remaining = 0;
                } else if ($loan->balance_remaining < $newAmount && $paidAmount > 0) {
                    $loan->status = 'partially_paid';
                } else {
                    $loan->status = 'unpaid';
                }
            }
            
            \Log::info('Loan Update - Final Status', [
                'balance_remaining' => $loan->balance_remaining,
                'status' => $loan->status,
            ]);
        } else if ($request->has('amount')) {
            // Amount provided but same as current, just update it
            $loan->amount = $request->amount;
        }

        // Save all changes
        $loan->save();

        \Log::info('Loan Update - After Save', [
            'amount' => $loan->amount,
            'balance_remaining' => $loan->balance_remaining,
            'status' => $loan->status,
        ]);

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

