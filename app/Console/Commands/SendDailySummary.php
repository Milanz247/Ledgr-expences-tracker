<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramBot;
use App\Models\Expense; // Assuming Expense model exists
use App\Models\Income;  // Assuming Income model exists
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SendDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily expense/income summary via Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bot = TelegramBot::first();

        // Check if bot exists, summary enabled, and topic selected
        if (!$bot || !$bot->daily_summary || !$bot->summary_topic_id) {
            $this->info('Daily summary disabled or not configured.');
            return;
        }

        // Check time (simple check: if current time is within 30 mins of scheduled time)
        // Note: For precise scheduling, this command should be run every minute via scheduler
        // But here we'll assume the scheduler handles the timing or we just run it.
        // Let's implement strict time checking if run frequently
        
        $scheduledTime = Carbon::createFromFormat('H:i:s', $bot->daily_summary_time . ':00');
        $now = Carbon::now();
        
        // Allow a window of execution (e.g., +/- 10 mins) if run via cron
        if (abs($now->diffInMinutes($scheduledTime, false)) > 10 && !$this->option('force')) {
           // Maybe implemented differently in Kernel.php schedule
        }

        // Calculate totals for today
        $today = Carbon::today();
        
        // Adjust these queries based on your actual database schema
        $totalExpense = \DB::table('expenses')->whereDate('date', $today)->sum('amount');
        $totalIncome = \DB::table('incomes')->whereDate('date', $today)->sum('amount'); 
        
        $balance = $totalIncome - $totalExpense;

        $message = "📅 *Daily Summary* (" . $today->format('Y-m-d') . ")\n\n" .
                   "📉 Total Expenses: " . number_format($totalExpense, 2) . "\n" .
                   "📈 Total Income: " . number_format($totalIncome, 2) . "\n" .
                   "----------------\n" .
                   "💰 Net: " . number_format($balance, 2);

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
            'chat_id' => $bot->chat_id,
            'text'    => $message,
            'parse_mode' => 'Markdown',
            'message_thread_id' => $bot->summary_topic_id
        ]);

        if ($response->successful()) {
            $this->info('Daily summary sent successfully.');
        } else {
            $this->error('Failed to send summary: ' . $response->body());
        }
    }
}
