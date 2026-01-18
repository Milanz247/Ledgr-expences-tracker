<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Budget;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = $request->user()->expenses()
            ->with(['category', 'bankAccount', 'fundSource', 'loan']);

        // Search filter (by description)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $q->where('description', 'like', '%' . $request->search . '%');
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->category_id);
        });

        // Date range filter
        $query->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
            $q->whereBetween('date', [$request->start_date, $request->end_date]);
        });

        // Single date filter (for quick filters like "today", "this week")
        $query->when($request->filled('date'), function ($q) use ($request) {
            $q->whereDate('date', $request->date);
        });

        // Source type filter (bank, fund, loan)
        $query->when($request->filled('source_type'), function ($q) use ($request) {
            if ($request->source_type === 'bank') {
                $q->whereNotNull('bank_account_id');
                // If specific bank account ID is provided
                $q->when($request->filled('source_id'), function ($q) use ($request) {
                    $q->where('bank_account_id', $request->source_id);
                });
            } elseif ($request->source_type === 'fund') {
                $q->whereNotNull('fund_source_id');
                $q->when($request->filled('source_id'), function ($q) use ($request) {
                    $q->where('fund_source_id', $request->source_id);
                });
            } elseif ($request->source_type === 'loan') {
                $q->whereNotNull('loan_id');
                $q->when($request->filled('source_id'), function ($q) use ($request) {
                    $q->where('loan_id', $request->source_id);
                });
            }
        });

        // Amount range filter
        $query->when($request->filled('min_amount'), function ($q) use ($request) {
            $q->where('amount', '>=', $request->min_amount);
        });
        $query->when($request->filled('max_amount'), function ($q) use ($request) {
            $q->where('amount', '<=', $request->max_amount);
        });

        // Sorting
        $sortField = $request->input('sort_by', 'date');
        $sortDirection = $request->input('sort_dir', 'desc');
        $allowedSorts = ['date', 'amount', 'created_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest('date');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);

        // If no pagination requested, return all (for backwards compatibility)
        if ($request->boolean('all')) {
            return response()->json($query->get());
        }

        $expenses = $query->paginate($perPage);

        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
            'loan_id' => 'nullable|exists:loans,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        // Ensure one payment source is selected
        if (!$request->bank_account_id && !$request->fund_source_id && !$request->loan_id) {
            return response()->json(['message' => 'Please select a payment source'], 422);
        }

        $paymentSources = array_filter([
            $request->bank_account_id,
            $request->fund_source_id,
            $request->loan_id
        ]);

        if (count($paymentSources) > 1) {
            return response()->json(['message' => 'Please select only one payment source'], 422);
        }

        // Handle Bank Account payment
        if ($request->bank_account_id) {
            $bankAccount = $request->user()->bankAccounts()->find($request->bank_account_id);
            if (!$bankAccount) {
                return response()->json(['message' => 'Bank account not found'], 404);
            }

            // Check if balance is sufficient
            if ($bankAccount->balance < $request->amount) {
                return response()->json(['message' => 'Insufficient bank account balance'], 422);
            }

            // Create expense
            $expense = $request->user()->expenses()->create($request->all());

            // Update bank account balance
            $bankAccount->balance -= $expense->amount;
            $bankAccount->save();

            // Update budget spent amount
            $this->updateBudgetSpent($request->user()->id, $expense->category_id, $expense->date, $expense->amount);

            // Load relationships
            $expense->load(['category', 'bankAccount']);

            return response()->json($expense, 201);
        }

        // Handle Fund Source payment
        if ($request->fund_source_id) {
            $fundSource = $request->user()->fundSources()->find($request->fund_source_id);
            if (!$fundSource) {
                return response()->json(['message' => 'Fund source not found'], 404);
            }

            // Check if fund source has sufficient balance
            if ($fundSource->amount < $request->amount) {
                return response()->json(['message' => 'Insufficient fund source balance'], 422);
            }

            // Create expense
            $expense = $request->user()->expenses()->create($request->all());

            // Update fund source balance
            $fundSource->amount -= $expense->amount;
            $fundSource->save();

            // Update budget spent amount
            $this->updateBudgetSpent($request->user()->id, $expense->category_id, $expense->date, $expense->amount);

            // Load relationships
            $expense->load(['category', 'fundSource']);

            return response()->json($expense, 201);
        }

        // Handle Loan payment
        if ($request->loan_id) {
            $loan = $request->user()->loans()->find($request->loan_id);
            if (!$loan) {
                return response()->json(['message' => 'Loan not found'], 404);
            }

            // Check if loan is marked as funding source
            if (!$loan->is_funding_source) {
                return response()->json(['message' => 'This loan is not configured as a funding source'], 422);
            }

            // Check if loan has sufficient available balance
            if ($loan->available_balance < $request->amount) {
                return response()->json([
                    'message' => 'Insufficient loan balance. Available: ' . number_format($loan->available_balance, 2)
                ], 422);
            }

            // Create expense
            $expense = $request->user()->expenses()->create($request->all());

            // Update budget spent amount
            $this->updateBudgetSpent($request->user()->id, $expense->category_id, $expense->date, $expense->amount);

            // Load relationships
            $expense->load(['category', 'loan']);

            return response()->json($expense, 201);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Expense $expense)
    {
        // Ensure user can only view their own expenses
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expense->load(['category', 'bankAccount', 'fundSource', 'loan']);

        return response()->json($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        // Ensure user can only update their own expenses
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'bank_account_id' => 'sometimes|required|exists:bank_accounts,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date',
        ]);

        // If amount or bank account changed, update balances
        if ($request->has('amount') || $request->has('bank_account_id')) {
            $oldBankAccount = $expense->bankAccount;
            $oldAmount = $expense->amount;

            // Restore old balance
            $oldBankAccount->balance += $oldAmount;
            $oldBankAccount->save();

            // Update expense
            $expense->update($request->all());

            // Deduct from new/same bank account
            $newBankAccount = $expense->bankAccount;
            $newBankAccount->balance -= $expense->amount;
            $newBankAccount->save();
        } else {
            $expense->update($request->all());
        }

        $expense->load(['category', 'bankAccount']);

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Expense $expense)
    {
        // Ensure user can only delete their own expenses
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Restore balance to appropriate source
        if ($expense->bank_account_id) {
            $bankAccount = $expense->bankAccount;
            $bankAccount->balance += $expense->amount;
            $bankAccount->save();
        }

        if ($expense->fund_source_id) {
            $fundSource = $expense->fundSource;
            $fundSource->amount += $expense->amount;
            $fundSource->save();
        }

        // Update budget spent amount (subtract)
        $this->updateBudgetSpent($request->user()->id, $expense->category_id, $expense->date, -$expense->amount);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Update budget spent amount for a given category and month
     */
    private function updateBudgetSpent($userId, $categoryId, $expenseDate, $amount)
    {
        $date = Carbon::parse($expenseDate);
        $month = $date->month;
        $year = $date->year;

        // Find or skip if no budget exists
        $budget = Budget::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($budget) {
            $budget->spent += $amount;
            $budget->save();
        }
    }
}
