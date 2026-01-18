<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetController extends Controller
{
    /**
     * Get all budgets for the current user (current month by default)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // Calculate date range for the month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $budgets = $user->budgets()
            ->with('category')
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->map(function ($budget) use ($user, $startDate, $endDate) {
                // Recalculate spent from actual expenses for this month
                $actualSpent = $user->expenses()
                    ->where('category_id', $budget->category_id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('amount');

                // Update budget spent if different
                if ($budget->spent != $actualSpent) {
                    $budget->spent = $actualSpent;
                    $budget->save();
                }

                return [
                    'id' => $budget->id,
                    'category' => [
                        'id' => $budget->category->id,
                        'name' => $budget->category->name,
                        'icon' => $budget->category->icon,
                        'color' => $budget->category->color,
                    ],
                    'amount' => floatval($budget->amount),
                    'spent' => floatval($budget->spent),
                    'rollover_amount' => floatval($budget->rollover_amount),
                    'total_budget' => $budget->total_budget,
                    'remaining' => $budget->remaining,
                    'percentage_used' => $budget->percentage_used,
                    'is_near_limit' => $budget->is_near_limit,
                    'is_exceeded' => $budget->is_exceeded,
                    'rollover_enabled' => $budget->rollover_enabled,
                    'month' => $budget->month,
                    'year' => $budget->year,
                ];
            });

        return response()->json(['data' => $budgets]);
    }

    /**
     * Create a new budget
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020',
            'rollover_enabled' => 'boolean',
            'alert_at_90_percent' => 'boolean',
        ]);

        $user = $request->user();

        // Check if budget already exists for this category, month, year
        $existing = $user->budgets()
            ->where('category_id', $validated['category_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Budget already exists for this category in this month',
            ], 422);
        }

        // Calculate spent so far for this month
        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $spent = $user->expenses()
            ->where('category_id', $validated['category_id'])
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $budget = $user->budgets()->create([
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'spent' => $spent,
            'month' => $validated['month'],
            'year' => $validated['year'],
            'rollover_enabled' => $validated['rollover_enabled'] ?? false,
            'alert_at_90_percent' => $validated['alert_at_90_percent'] ?? true,
        ]);

        return response()->json($budget->load('category'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Budget $budget)
    {
        // Authorization check
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($budget->load('category'));
    }

    /**
     * Update a budget
     */
    public function update(Request $request, Budget $budget)
    {
        // Authorization check
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount' => 'numeric|min:0',
            'rollover_enabled' => 'boolean',
            'alert_at_90_percent' => 'boolean',
        ]);

        $budget->update($validated);

        return response()->json($budget->load('category'));
    }

    /**
     * Delete a budget
     */
    public function destroy(Request $request, Budget $budget)
    {
        // Authorization check
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget deleted successfully']);
    }

    /**
     * Get budget overview with warnings
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $budgets = $user->budgets()
            ->with('category')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $totalBudgeted = $budgets->sum('total_budget');
        $totalSpent = $budgets->sum('spent');
        $totalRemaining = $totalBudgeted - $totalSpent;

        $warnings = $budgets->filter(function ($budget) {
            return $budget->is_near_limit || $budget->is_exceeded;
        })->values();

        return response()->json([
            'data' => [
                'total_budgeted' => floatval($totalBudgeted),
                'total_spent' => floatval($totalSpent),
                'total_remaining' => floatval($totalRemaining),
                'percentage_used' => $totalBudgeted > 0 ? ($totalSpent / $totalBudgeted) * 100 : 0,
                'budgets_count' => $budgets->count(),
                'warnings' => $warnings->map(function ($budget) {
                    return [
                        'id' => $budget->id,
                        'category_name' => $budget->category->name,
                        'message' => $budget->is_exceeded
                            ? 'Budget exceeded!'
                            : 'Near budget limit (90%)',
                        'percentage_used' => $budget->percentage_used,
                        'is_exceeded' => $budget->is_exceeded,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Process month-end rollover
     */
    public function processRollover(Request $request)
    {
        $user = $request->user();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Get previous month/year
        $previousDate = Carbon::now()->subMonth();
        $previousMonth = $previousDate->month;
        $previousYear = $previousDate->year;

        // Get budgets from previous month with rollover enabled
        $previousBudgets = $user->budgets()
            ->where('month', $previousMonth)
            ->where('year', $previousYear)
            ->where('rollover_enabled', true)
            ->get();

        foreach ($previousBudgets as $prevBudget) {
            $remaining = $prevBudget->remaining;

            if ($remaining > 0) {
                // Find or create budget for current month
                $currentBudget = $user->budgets()
                    ->firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'category_id' => $prevBudget->category_id,
                            'month' => $currentMonth,
                            'year' => $currentYear,
                        ],
                        [
                            'amount' => $prevBudget->amount,
                            'rollover_amount' => $remaining,
                            'rollover_enabled' => true,
                        ]
                    );

                // If budget already exists, add rollover
                if (!$currentBudget->wasRecentlyCreated) {
                    $currentBudget->rollover_amount += $remaining;
                    $currentBudget->save();
                }
            }
        }

        return response()->json(['message' => 'Rollover processed successfully']);
    }
}

