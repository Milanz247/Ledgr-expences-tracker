<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->user()->incomes()
            ->with(['category', 'bankAccount', 'fundSource']);

        // Search filter
        $query->when($request->filled('search'), function ($q) use ($request) {
            $q->where('description', 'like', '%' . $request->search . '%');
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->category_id);
        });

        // Date range filter
        $query->when($request->filled('start_date'), function ($q) use ($request) {
            $q->where('date', '>=', $request->start_date);
        });

        $query->when($request->filled('end_date'), function ($q) use ($request) {
            $q->where('date', '<=', $request->end_date);
        });

        // Source type filter (bank or fund)
        $query->when($request->filled('source_type'), function ($q) use ($request) {
            if ($request->source_type === 'bank') {
                $q->whereNotNull('bank_account_id');
            } elseif ($request->source_type === 'fund') {
                $q->whereNotNull('fund_source_id');
            }
        });

        // Specific source id filter
        $query->when($request->filled('source_id') && $request->filled('source_type'), function ($q) use ($request) {
            if ($request->source_type === 'bank') {
                $q->where('bank_account_id', $request->source_id);
            } elseif ($request->source_type === 'fund') {
                $q->where('fund_source_id', $request->source_id);
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
        $sortBy = $request->get('sort_by', 'date');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['date', 'amount', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('date');
        }

        // Return all for backwards compatibility, or paginate
        if ($request->has('all') && $request->all === 'true') {
            return response()->json($query->get());
        }

        $perPage = min($request->get('per_page', 15), 100);
        return response()->json($query->paginate($perPage));
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
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        // Ensure one destination is selected
        if (!$request->bank_account_id && !$request->fund_source_id) {
            return response()->json(['message' => 'Please select a destination (Bank Account or Fund Source)'], 422);
        }

        $destinations = array_filter([
            $request->bank_account_id,
            $request->fund_source_id,
        ]);

        if (count($destinations) > 1) {
            return response()->json(['message' => 'Please select only one destination'], 422);
        }

        DB::beginTransaction();

        try {
            // Create income record
            $income = $request->user()->incomes()->create($request->all());

            // Update destination balance
            if ($request->bank_account_id) {
                $bankAccount = $request->user()->bankAccounts()->find($request->bank_account_id);
                if (!$bankAccount) {
                    DB::rollBack();
                    return response()->json(['message' => 'Bank account not found'], 404);
                }

                $bankAccount->balance += $income->amount;
                $bankAccount->save();
            }

            if ($request->fund_source_id) {
                $fundSource = $request->user()->fundSources()->find($request->fund_source_id);
                if (!$fundSource) {
                    DB::rollBack();
                    return response()->json(['message' => 'Fund source not found'], 404);
                }

                $fundSource->amount += $income->amount;
                $fundSource->save();
            }

            DB::commit();

            // Load relationships
            $income->load(['category', 'bankAccount', 'fundSource']);

            return response()->json($income, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record income',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Income $income)
    {
        // Ensure user can only view their own incomes
        if ($income->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $income->load(['category', 'bankAccount', 'fundSource']);

        return response()->json($income);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Income $income)
    {
        // Ensure user can only update their own incomes
        if ($income->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date',
        ]);

        DB::beginTransaction();

        try {
            $oldAmount = $income->amount;

            // Revert old balance
            if ($income->bank_account_id) {
                $oldBankAccount = $income->bankAccount;
                $oldBankAccount->balance -= $oldAmount;
                $oldBankAccount->save();
            }

            if ($income->fund_source_id) {
                $oldFundSource = $income->fundSource;
                $oldFundSource->amount -= $oldAmount;
                $oldFundSource->save();
            }

            // Update income
            $income->update($request->all());

            // Apply new balance
            if ($income->bank_account_id) {
                $newBankAccount = $income->bankAccount;
                $newBankAccount->balance += $income->amount;
                $newBankAccount->save();
            }

            if ($income->fund_source_id) {
                $newFundSource = $income->fundSource;
                $newFundSource->amount += $income->amount;
                $newFundSource->save();
            }

            DB::commit();

            $income->load(['category', 'bankAccount', 'fundSource']);

            return response()->json($income);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update income',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Income $income)
    {
        // Ensure user can only delete their own incomes
        if ($income->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            // Restore balance
            if ($income->bank_account_id) {
                $bankAccount = $income->bankAccount;
                $bankAccount->balance -= $income->amount;
                $bankAccount->save();
            }

            if ($income->fund_source_id) {
                $fundSource = $income->fundSource;
                $fundSource->amount -= $income->amount;
                $fundSource->save();
            }

            $income->delete();

            DB::commit();

            return response()->json([
                'message' => 'Income deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete income',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
