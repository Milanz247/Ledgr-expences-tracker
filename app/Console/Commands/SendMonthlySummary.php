<?php

namespace App\Console\Commands;

use App\Models\TelegramBot;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Budget;
use App\Models\Loan;
use App\Models\Category;
use App\Models\BankAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendMonthlySummary extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:monthly-summary {--force : Force send regardless of day/time}';

    /**
     * The console command description.
     */
    protected $description = 'Send monthly financial summary to Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bot = TelegramBot::first();

        if (!$bot || !$bot->token || !$bot->chat_id) {
            $this->error('Telegram bot not configured.');
            return 1;
        }

        if (!$bot->monthly_summary && !$this->option('force')) {
            $this->info('Monthly summary is disabled.');
            return 0;
        }

        $now = Carbon::now();

        // Check if it's the right day and time
        if (!$this->option('force')) {
            $scheduledDay = $bot->monthly_summary_day ?? 1;
            $scheduledTime = Carbon::parse($bot->monthly_summary_time ?? '09:00');

            if ($now->day != $scheduledDay) {
                $this->info("Not the scheduled day. Today is day {$now->day}, scheduled for day {$scheduledDay}.");
                return 0;
            }

            if (abs($now->diffInMinutes($scheduledTime, false)) > 5) {
                $this->info('Not the scheduled time yet.');
                return 0;
            }
        }

        try {
            $summary = $this->generateMonthlySummary();
            $this->sendToTelegram($bot, $summary);
            $this->info('Monthly summary sent successfully!');
            return 0;
        } catch (\Exception $e) {
            Log::error('Failed to send monthly summary: ' . $e->getMessage());
            $this->error('Failed to send summary: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate the monthly summary data (for previous month)
     */
    private function generateMonthlySummary(): string
    {
        // Report for last month
        $lastMonth = Carbon::now()->subMonth();
        $startOfLastMonth = $lastMonth->copy()->startOfMonth();
        $endOfLastMonth = $lastMonth->copy()->endOfMonth();

        // Current month for comparison
        $startOfThisMonth = Carbon::now()->startOfMonth();
        $endOfThisMonth = Carbon::now()->endOfMonth();

        // Last month totals
        $lastMonthExpenses = Expense::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->sum('amount');
        $lastMonthIncomes = Income::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->sum('amount');
        $lastMonthNet = $lastMonthIncomes - $lastMonthExpenses;
        $expenseCount = Expense::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->count();
        $incomeCount = Income::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->count();

        // Previous month (for comparison)
        $prevMonth = $lastMonth->copy()->subMonth();
        $startOfPrevMonth = $prevMonth->copy()->startOfMonth();
        $endOfPrevMonth = $prevMonth->copy()->endOfMonth();

        $prevMonthExpenses = Expense::whereBetween('date', [$startOfPrevMonth, $endOfPrevMonth])->sum('amount');
        $prevMonthIncomes = Income::whereBetween('date', [$startOfPrevMonth, $endOfPrevMonth])->sum('amount');

        // Calculate changes
        $expenseChange = $prevMonthExpenses > 0
            ? (($lastMonthExpenses - $prevMonthExpenses) / $prevMonthExpenses) * 100
            : 0;
        $incomeChange = $prevMonthIncomes > 0
            ? (($lastMonthIncomes - $prevMonthIncomes) / $prevMonthIncomes) * 100
            : 0;

        // Category breakdown
        $categoryExpenses = Expense::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get();

        // Daily averages
        $daysInLastMonth = $lastMonth->daysInMonth;
        $avgDailyExpense = $daysInLastMonth > 0 ? $lastMonthExpenses / $daysInLastMonth : 0;
        $avgDailyIncome = $daysInLastMonth > 0 ? $lastMonthIncomes / $daysInLastMonth : 0;

        // Highest spending day
        $highestSpendingDay = Expense::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->selectRaw('date, SUM(amount) as total')
            ->groupBy('date')
            ->orderByDesc('total')
            ->first();

        // Budget performance
        $budgetPerformance = [];
        $budgets = Budget::with('category')->get();
        foreach ($budgets as $budget) {
            $spent = Expense::where('category_id', $budget->category_id)
                ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
                ->sum('amount');

            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
            $budgetPerformance[] = [
                'category' => $budget->category->name ?? 'Unknown',
                'spent' => $spent,
                'budget' => $budget->amount,
                'percentage' => round($percentage, 1),
                'status' => $percentage <= 100 ? 'within' : 'exceeded'
            ];
        }

        // Sort by percentage descending
        usort($budgetPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

        // Bank accounts summary
        $bankAccounts = BankAccount::all();
        $totalBankBalance = $bankAccounts->sum('balance');

        // Loans summary
        $activeLoans = Loan::where('status', 'active')->get();
        $totalLoanRemaining = $activeLoans->sum('balance_remaining');
        $loansPaymentsThisMonth = 0; // Could calculate from repayments table

        // Savings rate
        $savingsRate = $lastMonthIncomes > 0
            ? ($lastMonthNet / $lastMonthIncomes) * 100
            : 0;

        // Build the message
        $message = "📈 *Monthly Financial Report*\n";
        $message .= "📅 " . $lastMonth->format('F Y') . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        // Overview
        $message .= "💼 *Financial Overview*\n";
        $message .= "┌─────────────────────────\n";
        $message .= "│ 💰 Total Income: Rs " . number_format($lastMonthIncomes, 2) . "\n";
        $incomeChangeEmoji = $incomeChange >= 0 ? '📈' : '📉';
        $message .= "│    {$incomeChangeEmoji} " . ($incomeChange >= 0 ? '+' : '') . number_format($incomeChange, 1) . "% vs prev month\n";
        $message .= "│\n";
        $message .= "│ 💸 Total Expenses: Rs " . number_format($lastMonthExpenses, 2) . "\n";
        $expenseChangeEmoji = $expenseChange <= 0 ? '📈' : '📉';
        $message .= "│    {$expenseChangeEmoji} " . ($expenseChange >= 0 ? '+' : '') . number_format($expenseChange, 1) . "% vs prev month\n";
        $message .= "│\n";
        $netEmoji = $lastMonthNet >= 0 ? '✅' : '🔴';
        $message .= "│ {$netEmoji} Net Savings: Rs " . number_format($lastMonthNet, 2) . "\n";
        $savingsEmoji = $savingsRate >= 20 ? '🎯' : ($savingsRate >= 10 ? '📊' : '⚠️');
        $message .= "│ {$savingsEmoji} Savings Rate: " . number_format($savingsRate, 1) . "%\n";
        $message .= "└─────────────────────────\n\n";

        // Transaction Summary
        $message .= "📝 *Transaction Summary*\n";
        $message .= "├ Total Transactions: " . ($expenseCount + $incomeCount) . "\n";
        $message .= "├ Expense Transactions: {$expenseCount}\n";
        $message .= "├ Income Transactions: {$incomeCount}\n";
        $message .= "├ Avg Daily Expense: Rs " . number_format($avgDailyExpense, 2) . "\n";
        $message .= "└ Avg Daily Income: Rs " . number_format($avgDailyIncome, 2) . "\n\n";

        // Highest Spending Day
        if ($highestSpendingDay) {
            $message .= "📅 *Highest Spending Day*\n";
            $message .= "└ " . Carbon::parse($highestSpendingDay->date)->format('M j') . ": Rs " . number_format($highestSpendingDay->total, 2) . "\n\n";
        }

        // Expense Breakdown by Category
        if ($categoryExpenses->count() > 0) {
            $message .= "🏷️ *Expense Breakdown*\n";
            $totalForPercentage = $lastMonthExpenses > 0 ? $lastMonthExpenses : 1;

            foreach ($categoryExpenses->take(7) as $index => $cat) {
                $prefix = $index === min(6, $categoryExpenses->count() - 1) ? '└' : '├';
                $catName = $cat->category->name ?? 'Other';
                $percentage = ($cat->total / $totalForPercentage) * 100;
                $bar = $this->generateProgressBar($percentage);
                $message .= "{$prefix} {$catName}\n";
                $message .= "   {$bar} " . number_format($percentage, 1) . "%\n";
                $message .= "   Rs " . number_format($cat->total, 2) . " ({$cat->count} txns)\n";
            }

            if ($categoryExpenses->count() > 7) {
                $otherTotal = $categoryExpenses->skip(7)->sum('total');
                $otherPercentage = ($otherTotal / $totalForPercentage) * 100;
                $message .= "└ Others: Rs " . number_format($otherTotal, 2) . " (" . number_format($otherPercentage, 1) . "%)\n";
            }
            $message .= "\n";
        }

        // Budget Performance
        if (count($budgetPerformance) > 0) {
            $message .= "🎯 *Budget Performance*\n";

            $withinBudget = array_filter($budgetPerformance, fn($b) => $b['status'] === 'within');
            $exceededBudget = array_filter($budgetPerformance, fn($b) => $b['status'] === 'exceeded');

            if (count($exceededBudget) > 0) {
                $message .= "*⚠️ Exceeded:*\n";
                foreach (array_slice($exceededBudget, 0, 3) as $budget) {
                    $message .= "├ 🔴 {$budget['category']}: {$budget['percentage']}%\n";
                    $message .= "│    Rs " . number_format($budget['spent'], 2) . " / Rs " . number_format($budget['budget'], 2) . "\n";
                }
            }

            if (count($withinBudget) > 0) {
                $message .= "*✅ Within Budget:*\n";
                foreach (array_slice($withinBudget, 0, 3) as $budget) {
                    $message .= "├ 🟢 {$budget['category']}: {$budget['percentage']}%\n";
                }
            }
            $message .= "\n";
        }

        // Account Balances
        if ($bankAccounts->count() > 0 || $activeLoans->count() > 0) {
            $message .= "🏦 *Current Balances*\n";

            if ($bankAccounts->count() > 0) {
                $message .= "├ Bank Accounts: Rs " . number_format($totalBankBalance, 2) . "\n";
                foreach ($bankAccounts->take(3) as $account) {
                    $message .= "│  └ {$account->name}: Rs " . number_format($account->balance, 2) . "\n";
                }
            }

            if ($activeLoans->count() > 0) {
                $message .= "├ Active Loans: {$activeLoans->count()}\n";
                $message .= "│  └ Remaining: Rs " . number_format($totalLoanRemaining, 2) . "\n";
            }

            $netWorth = $totalBankBalance - $totalLoanRemaining;
            $nwEmoji = $netWorth >= 0 ? '💎' : '⚠️';
            $message .= "└ {$nwEmoji} Net Worth: Rs " . number_format($netWorth, 2) . "\n\n";
        }

        // Tips/Insights
        $message .= "💡 *Insights*\n";

        if ($savingsRate < 10) {
            $message .= "├ ⚠️ Low savings rate. Try to save at least 20%.\n";
        } elseif ($savingsRate >= 30) {
            $message .= "├ 🌟 Excellent savings rate! Keep it up!\n";
        }

        if ($expenseChange > 20) {
            $message .= "├ 📈 Expenses increased significantly this month.\n";
        } elseif ($expenseChange < -10) {
            $message .= "├ 👏 Great job reducing expenses!\n";
        }

        if (count($exceededBudget ?? []) > 0) {
            $message .= "├ 🎯 Review exceeded budgets for next month.\n";
        }

        $message .= "└ 📊 Keep tracking for better insights!\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🤖 _Ledgr Monthly Report_";

        return $message;
    }

    /**
     * Generate a simple text progress bar
     */
    private function generateProgressBar(float $percentage): string
    {
        $filled = min(10, round($percentage / 10));
        $empty = 10 - $filled;
        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }

    /**
     * Send message to Telegram
     */
    private function sendToTelegram(TelegramBot $bot, string $message): void
    {
        $payload = [
            'chat_id' => $bot->chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ];

        // Use monthly summary topic if set, otherwise fall back to summary_topic_id
        $topicId = $bot->monthly_summary_topic_id ?? $bot->summary_topic_id;
        if ($topicId) {
            $payload['message_thread_id'] = $topicId;
        }

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", $payload);

        if (!$response->successful()) {
            throw new \Exception('Telegram API error: ' . ($response->json()['description'] ?? 'Unknown error'));
        }
    }
}
