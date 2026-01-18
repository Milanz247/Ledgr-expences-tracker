<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get expenses grouped by category for the current month
     */
    public function expensesByCategory(Request $request)
    {
        $user = $request->user();

        // Define color palette for categories
        $colors = [
            '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
            '#06b6d4', '#6366f1', '#f97316', '#14b8a6', '#a855f7',
            '#ef4444', '#f43f5e', '#eab308', '#22c55e', '#0ea5e9'
        ];

        // Get expenses for current month grouped by category
        $expensesByCategory = $user->expenses()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category')
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item, $index) use ($colors) {
                return [
                    'category' => $item->category->name,
                    'amount' => floatval($item->total),
                    'fill' => $colors[$index % count($colors)],
                ];
            })
            ->values();

        return response()->json($expensesByCategory);
    }

    /**
     * Get expenses grouped by category for a specific month
     */
    /**
     * Get aggregated report data for the Financial Command Center.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 1. Overview Tab Data
        
        // Cash Flow Trend (Last 6 Months)
        $cashFlow = collect(range(5, 0))->map(function ($i) use ($user) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $year = $date->year;
            $month = $date->month;

            $income = $user->incomes()
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $expense = $user->expenses()
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            return [
                'name' => $monthName,
                'income' => (float)$income,
                'expense' => (float)$expense,
            ];
        });

        // Liquidity Mix
        $totalBank = $user->bankAccounts()->sum('balance');
        $totalCash = $user->fundSources()->sum('amount');
        
        $liquidityMix = [
            ['name' => 'Bank Accounts', 'value' => (float)$totalBank, 'fill' => '#10b981'], // Emerald-500
            ['name' => 'Cash & Wallets', 'value' => (float)$totalCash, 'fill' => '#f59e0b'], // Amber-500
        ];

        // Net Worth (Simple Calculation: Assets - Liabilities)
        // Assets = Bank + Cash
        // Liabilities = Active Loans + Remaining Installment Amounts
        $totalAssets = $totalBank + $totalCash;
        
        $activeLoansAmount = $user->loans()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->get()
            ->sum(function($loan) {
                return $loan->balance_remaining; // Directly use balance_remaining as liability
            });

        $activeInstallmentsAmount = $user->installments()
            ->where('status', 'ongoing')
            ->get()
            ->sum(function($inst) {
                return $inst->total_amount - ($inst->monthly_amount * $inst->paid_months);
            });
            
        $totalLiabilities = $activeLoansAmount + $activeInstallmentsAmount;
        $netWorth = $totalAssets - $totalLiabilities;


        // 2. Budgets & Recurring Tab Data

        // Budget Adherence
        $budgets = $user->budgets()
            ->with(['category', 'expenses' => function ($query) {
                $query->whereMonth('date', Carbon::now()->month)
                      ->whereYear('date', Carbon::now()->year);
            }])
            ->get()
            ->map(function ($budget) {
                $spent = $budget->expenses->sum('amount');
                $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
                
                return [
                    'id' => $budget->id,
                    'category' => $budget->category->name,
                    'limit' => (float)$budget->amount,
                    'spent' => (float)$spent,
                    'percentage' => round($percentage, 1),
                    'color' => $budget->category->color,
                ];
            });

        // Recurring Summary
        $recurringExpenses = $user->recurringTransactions()
            ->where('is_active', true)
            // Filter only expense categories if needed, or assume all are expenses
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense');
            })
            ->get();
            
        $monthlyRecurringCost = $recurringExpenses->sum('amount');
        
        $upcomingRecurring = $recurringExpenses
            ->sortBy('next_due_date')
            ->take(3)
            ->map(function ($rec) {
                return [
                    'id' => $rec->id,
                    'name' => $rec->name, // Changed from description to name as per migration
                    'amount' => (float)$rec->amount,
                    'due_date' => $rec->next_due_date,
                ];
            })
            ->values();


        // 3. Debts & Assets Tab Data

        // Debt Breakdown
        $debtDistribution = [
            ['name' => 'Loans', 'value' => (float)$activeLoansAmount, 'fill' => '#ef4444'], // Red-500
            ['name' => 'Installments', 'value' => (float)$activeInstallmentsAmount, 'fill' => '#f97316'], // Orange-500
        ];

        // Loan Progress
        $loanProgress = $user->loans()
             ->whereIn('status', ['unpaid', 'partially_paid'])
            ->get()
            ->map(function ($loan) {
                // Determine total amount. Migration has 'amount' (principal) and 'balance_remaining'
                $total = (float)$loan->amount; 
                $remaining = (float)$loan->balance_remaining;
                $paid = $total - $remaining;
                
                $percentage = $total > 0 ? ($paid / $total) * 100 : 0;

                return [
                    'id' => $loan->id,
                    'name' => $loan->lender_name,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'percentage' => round($percentage, 1),
                    'due_date' => $loan->due_date ? Carbon::parse($loan->due_date)->format('Y-m-d') : null, 
                ];
            });

        // Installment Progress
        $installmentProgress = $user->installments()
            ->where('status', 'ongoing')
            ->with('category')
            ->get()
            ->map(function ($inst) {
                $paidAmount = $inst->monthly_amount * $inst->paid_months;
                $percentage = $inst->total_amount > 0 ? ($paidAmount / $inst->total_amount) * 100 : 0;
                
                // Estimate finish date
                $remainingMonths = $inst->total_months - $inst->paid_months;
                $finishDate = Carbon::parse($inst->start_date)->addMonths($inst->total_months);
                
                return [
                    'id' => $inst->id,
                    'name' => $inst->item_name,
                    'category' => $inst->category->name,
                    'total' => (float)$inst->total_amount,
                    'paid' => (float)$paidAmount,
                    'remaining' => (float)($inst->total_amount - $paidAmount),
                    'percentage' => round($percentage, 1),
                    'finish_date' => $finishDate->toDateString(),
                ];
            });
            
        // Asset Ratio Data
        $assetRatio = [
            ['name' => 'Net Assets', 'value' => max(0, $totalAssets - $totalLiabilities), 'fill' => '#10b981'],
            ['name' => 'Borrowed', 'value' => (float)$totalLiabilities, 'fill' => '#64748b'],
        ];


        return response()->json([
            'overview' => [
                'cash_flow' => $cashFlow,
                'liquidity_mix' => $liquidityMix,
                'net_worth' => (float)$netWorth,
                'total_assets' => (float)$totalAssets,
                'total_liabilities' => (float)$totalLiabilities,
            ],
            'budgets_recurring' => [
                'budget_adherence' => $budgets,
                'monthly_recurring_cost' => (float)$monthlyRecurringCost,
                'upcoming_recurring' => $upcomingRecurring,
            ],
            'debts_assets' => [
                'debt_distribution' => $debtDistribution,
                'loan_progress' => $loanProgress,
                'installment_progress' => $installmentProgress,
                'asset_ratio' => $assetRatio,
            ],
        ]);
    }
}
