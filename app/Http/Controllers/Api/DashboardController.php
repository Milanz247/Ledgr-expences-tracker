<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        // Always use current month
        $startDate = Carbon::now()->startOfMonth()->toDateString();
        $endDate = Carbon::now()->endOfMonth()->toDateString();

        // Get total balance from all bank accounts
        $totalBankBalance = $user->bankAccounts()->sum('balance');

        // Get total balance from all fund sources
        $totalFundBalance = $user->fundSources()->sum('amount');

        // Total balance (bank + fund sources)
        $totalBalance = $totalBankBalance + $totalFundBalance;

        // Get income for current month (from incomes table)
        $monthlyIncome = $user->incomes()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Get expenses for current month
        $monthlyExpenses = $user->expenses()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Get total outstanding debt from loans
        $totalDebt = $user->loans()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->sum('balance_remaining');

        // Get recent transactions
        $recentTransactions = $user->expenses()
            ->with(['category', 'bankAccount', 'fundSource', 'loan'])
            ->latest('date')
            ->limit(5)
            ->get();

        // Get category-wise breakdown for current month
        $categoryBreakdown = $user->expenses()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category->name,
                    'category_icon' => $item->category->icon,
                    'total' => $item->total,
                ];
            });

        // Get monthly spending trend (last 6 months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $income = $user->incomes()
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount');

            $expenses = $user->expenses()
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount');

            $monthlyTrend[] = [
                'month' => $date->format('M Y'),
                'income' => floatval($income),
                'expenses' => floatval($expenses),
            ];
        }

        // Get upcoming recurring bills (next 7 days)
        $upcomingBills = $user->recurringTransactions()
            ->with(['category', 'bankAccount', 'fundSource'])
            ->where('is_active', true)
            ->whereBetween('next_due_date', [
                Carbon::today(),
                Carbon::today()->addDays(7)
            ])
            ->orderBy('next_due_date')
            ->get()
            ->map(function ($recurring) {
                return [
                    'id' => $recurring->id,
                    'name' => $recurring->name,
                    'amount' => floatval($recurring->amount),
                    'category' => [
                        'name' => $recurring->category->name,
                        'icon' => $recurring->category->icon,
                    ],
                    'next_due_date' => $recurring->next_due_date->toDateString(),
                    'days_until_due' => Carbon::today()->diffInDays($recurring->next_due_date, false),
                    'payment_source' => $recurring->bank_account_id
                        ? ['type' => 'bank', 'name' => $recurring->bankAccount->bank_name]
                        : ['type' => 'fund', 'name' => $recurring->fundSource->source_name],
                ];
            });

        return response()->json([
            'total_balance' => floatval($totalBalance),
            'total_bank_balance' => floatval($totalBankBalance),
            'total_fund_balance' => floatval($totalFundBalance),
            'monthly_income' => floatval($monthlyIncome),
            'monthly_expenses' => floatval($monthlyExpenses),
            'total_debt' => floatval($totalDebt),
            'recent_transactions' => $recentTransactions,
            'category_breakdown' => $categoryBreakdown,
            'monthly_trend' => $monthlyTrend,
            'upcoming_bills' => $upcomingBills,
            'upcoming_installments' => $user->installments()
                ->where('status', 'ongoing')
                ->with('category')
                ->get()
                ->map(function ($inst) {
                    $dueDate = Carbon::parse($inst->start_date)->addMonths($inst->paid_months);
                    return [
                        'id' => $inst->id,
                        'name' => $inst->item_name,
                        'amount' => floatval($inst->monthly_amount),
                        'category_name' => $inst->category->name,
                        'due_date' => $dueDate->toDateString(),
                        'days_left' => Carbon::now()->diffInDays($dueDate, false),
                    ];
                })
                ->filter(fn($i) => $i['days_left'] >= 0 && $i['days_left'] <= 30) // Show next 30 days due
                ->values(),
        ]);
    }
}
