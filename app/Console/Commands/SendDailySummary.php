<?php

namespace App\Console\Commands;

use App\Models\TelegramBot;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Budget;
use App\Models\Loan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:daily-summary {--force : Force send regardless of time}';

    /**
     * The console command description.
     */
    protected $description = 'Send daily financial summary to Telegram';

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

        if (!$bot->daily_summary && !$this->option('force')) {
            $this->info('Daily summary is disabled.');
            return 0;
        }

        // Check if it's the right time (within 5 minutes of scheduled time)
        if (!$this->option('force')) {
            $scheduledTime = Carbon::parse($bot->daily_summary_time);
            $now = Carbon::now();

            if (abs($now->diffInMinutes($scheduledTime, false)) > 5) {
                $this->info('Not the scheduled time yet.');
                return 0;
            }
        }

        try {
            $summary = $this->generateDailySummary();
            $this->sendToTelegram($bot, $summary);
            $this->info('Daily summary sent successfully!');
            return 0;
        } catch (\Exception $e) {
            Log::error('Failed to send daily summary: ' . $e->getMessage());
            $this->error('Failed to send summary: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate the daily summary data
     */
    private function generateDailySummary(): string
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Today's transactions
        $todayExpenses = Expense::whereDate('date', $today)->sum('amount');
        $todayIncomes = Income::whereDate('date', $today)->sum('amount');
        $todayExpenseCount = Expense::whereDate('date', $today)->count();
        $todayIncomeCount = Income::whereDate('date', $today)->count();

        // This month's totals
        $monthExpenses = Expense::whereBetween('date', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthIncomes = Income::whereBetween('date', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthNet = $monthIncomes - $monthExpenses;

        // Top expense categories today
        $topCategories = Expense::whereDate('date', $today)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category')
            ->orderByDesc('total')
            ->limit(3)
            ->get();

        // Budget alerts
        $budgetAlerts = [];
        $budgets = Budget::with('category')->get();
        foreach ($budgets as $budget) {
            $spent = Expense::where('category_id', $budget->category_id)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;

            if ($percentage >= 80) {
                $budgetAlerts[] = [
                    'category' => $budget->category->name ?? 'Unknown',
                    'spent' => $spent,
                    'budget' => $budget->amount,
                    'percentage' => round($percentage, 1)
                ];
            }
        }

        // Active loans
        $activeLoans = Loan::where('status', 'active')->count();
        $totalLoanAmount = Loan::where('status', 'active')->sum('balance_remaining');

        // Build the message
        $message = "📊 *Daily Financial Summary*\n";
        $message .= "📅 " . $today->format('l, F j, Y') . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        // Today's Activity
        $message .= "🕐 *Today's Activity*\n";
        $message .= "├ 💸 Expenses: Rs " . number_format($todayExpenses, 2) . " ({$todayExpenseCount} transactions)\n";
        $message .= "├ 💰 Income: Rs " . number_format($todayIncomes, 2) . " ({$todayIncomeCount} transactions)\n";
        $todayNet = $todayIncomes - $todayExpenses;
        $netEmoji = $todayNet >= 0 ? '📈' : '📉';
        $message .= "└ {$netEmoji} Net: Rs " . number_format($todayNet, 2) . "\n\n";

        // Month Progress
        $daysInMonth = $today->daysInMonth;
        $dayOfMonth = $today->day;
        $monthProgress = round(($dayOfMonth / $daysInMonth) * 100);

        $message .= "📆 *Month Progress* ({$monthProgress}% complete)\n";
        $message .= "├ 💸 Total Expenses: Rs " . number_format($monthExpenses, 2) . "\n";
        $message .= "├ 💰 Total Income: Rs " . number_format($monthIncomes, 2) . "\n";
        $monthNetEmoji = $monthNet >= 0 ? '✅' : '⚠️';
        $message .= "└ {$monthNetEmoji} Net Savings: Rs " . number_format($monthNet, 2) . "\n\n";

        // Daily Averages
        $avgDailyExpense = $dayOfMonth > 0 ? $monthExpenses / $dayOfMonth : 0;
        $avgDailyIncome = $dayOfMonth > 0 ? $monthIncomes / $dayOfMonth : 0;

        $message .= "📈 *Daily Averages*\n";
        $message .= "├ Avg Expense: Rs " . number_format($avgDailyExpense, 2) . "/day\n";
        $message .= "└ Avg Income: Rs " . number_format($avgDailyIncome, 2) . "/day\n\n";

        // Top Categories Today
        if ($topCategories->count() > 0) {
            $message .= "🏷️ *Top Spending Today*\n";
            foreach ($topCategories as $index => $cat) {
                $prefix = $index === $topCategories->count() - 1 ? '└' : '├';
                $catName = $cat->category->name ?? 'Other';
                $message .= "{$prefix} {$catName}: Rs " . number_format($cat->total, 2) . "\n";
            }
            $message .= "\n";
        }

        // Budget Alerts
        if (count($budgetAlerts) > 0) {
            $message .= "🚨 *Budget Alerts*\n";
            foreach ($budgetAlerts as $index => $alert) {
                $prefix = $index === count($budgetAlerts) - 1 ? '└' : '├';
                $emoji = $alert['percentage'] >= 100 ? '🔴' : '🟡';
                $message .= "{$prefix} {$emoji} {$alert['category']}: {$alert['percentage']}% used\n";
                $message .= "   (Rs " . number_format($alert['spent'], 2) . " / Rs " . number_format($alert['budget'], 2) . ")\n";
            }
            $message .= "\n";
        }

        // Loans Summary
        if ($activeLoans > 0) {
            $message .= "💳 *Active Loans*: {$activeLoans}\n";
            $message .= "└ Remaining: Rs " . number_format($totalLoanAmount, 2) . "\n\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🤖 _Ledgr Daily Summary_";

        return $message;
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

        if ($bot->summary_topic_id) {
            $payload['message_thread_id'] = $bot->summary_topic_id;
        }

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", $payload);

        if (!$response->successful()) {
            throw new \Exception('Telegram API error: ' . ($response->json()['description'] ?? 'Unknown error'));
        }
    }
}
