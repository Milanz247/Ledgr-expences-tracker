<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RecurringTransactionController extends Controller
{
    /**
     * Get all recurring transactions for the current user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isActive = $request->input('is_active');

        $query = $user->recurringTransactions()
            ->with(['category', 'bankAccount', 'fundSource']);

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $transactions = $query->orderBy('next_due_date')->get();

        return response()->json(['data' => $transactions]);
    }

    /**
     * Create a new recurring transaction
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notify_3_days_before' => 'boolean',
        ]);

        $user = $request->user();

        // Calculate first due date
        $nextDueDate = Carbon::parse($validated['start_date']);

        $recurring = $user->recurringTransactions()->create([
            ...$validated,
            'next_due_date' => $nextDueDate,
            'is_active' => true,
        ]);

        return response()->json(['data' => $recurring->load(['category', 'bankAccount', 'fundSource'])], 201);
    }

    /**
     * Get a specific recurring transaction
     */
    public function show(Request $request, RecurringTransaction $recurringTransaction)
    {
        // Authorization check
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $recurringTransaction->load(['category', 'bankAccount', 'fundSource'])]);
    }

    /**
     * Update a recurring transaction
     */
    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        // Authorization check
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'amount' => 'numeric|min:0',
            'category_id' => 'exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
            'frequency' => 'in:daily,weekly,monthly,yearly',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
            'notify_3_days_before' => 'boolean',
        ]);

        $recurringTransaction->update($validated);

        return response()->json(['data' => $recurringTransaction->load(['category', 'bankAccount', 'fundSource'])]);
    }

    /**
     * Delete a recurring transaction
     */
    public function destroy(Request $request, RecurringTransaction $recurringTransaction)
    {
        // Authorization check
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recurringTransaction->delete();

        return response()->json(['message' => 'Recurring transaction deleted successfully']);
    }

    /**
     * Get upcoming bills (next 7 days)
     */
    public function upcomingBills(Request $request)
    {
        $user = $request->user();
        $daysAhead = (int) $request->input('days', 7);

        $upcoming = $user->recurringTransactions()
            ->with(['category', 'bankAccount', 'fundSource'])
            ->where('is_active', true)
            ->whereBetween('next_due_date', [
                Carbon::today(),
                Carbon::today()->addDays($daysAhead)
            ])
            ->orderBy('next_due_date')
            ->get();

        return response()->json(['data' => $upcoming]);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(Request $request, RecurringTransaction $recurringTransaction)
    {
        // Authorization check
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recurringTransaction->is_active = !$recurringTransaction->is_active;
        $recurringTransaction->save();

        return response()->json(['data' => $recurringTransaction]);
    }
}

