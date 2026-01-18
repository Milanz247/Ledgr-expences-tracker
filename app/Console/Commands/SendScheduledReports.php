<?php

namespace App\Console\Commands;

use App\Mail\ExpenseReportMail;
use App\Models\ReportSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send {--frequency=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled expense reports to users based on their frequency settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $frequency = $this->option('frequency');
        $today = Carbon::now();

        // Determine which reports to send based on frequency
        $shouldSendDaily = $frequency === 'daily' || (!$frequency && $today->hour === 21); // 9 PM
        $shouldSendWeekly = $frequency === 'weekly' || (!$frequency && $today->isMonday() && $today->hour === 9); // Monday 9 AM
        $shouldSendMonthly = $frequency === 'monthly' || (!$frequency && $today->day === 1 && $today->hour === 9); // 1st of month 9 AM

        // Get all enabled report settings
        $settings = ReportSetting::where('is_enabled', true);

        if ($frequency === 'daily') {
            $settings = $settings->where('frequency', 'daily');
        } elseif ($frequency === 'weekly') {
            $settings = $settings->where('frequency', 'weekly');
        } elseif ($frequency === 'monthly') {
            $settings = $settings->where('frequency', 'monthly');
        }

        $count = 0;
        $failed = 0;

        foreach ($settings->get() as $setting) {
            try {
                $startDate = $this->getStartDate($setting->frequency);
                $endDate = Carbon::now();

                // Get user's expenses for the period
                $expenses = $setting->user->expenses()
                    ->with(['category', 'bankAccount', 'fundSource'])
                    ->whereBetween('date', [$startDate, $endDate])
                    ->orderByDesc('amount')
                    ->get();

                // Skip if no expenses
                if ($expenses->isEmpty()) {
                    $this->info("No expenses for user {$setting->user->id} in period {$setting->frequency}. Skipping.");
                    continue;
                }

                // Get category breakdown
                $categoryBreakdown = $setting->user->expenses()
                    ->select('category_id', DB::raw('SUM(amount) as total'))
                    ->with('category')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->groupBy('category_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'category_name' => $item->category->name,
                            'total' => $item->total,
                        ];
                    });

                // Get summary
                $totalExpenses = $setting->user->expenses()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('amount');

                $totalIncome = $setting->user->fundSources()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');

                $reportData = [
                    'user_name' => $setting->user->name,
                    'period' => $this->getPeriodLabel($setting->frequency),
                    'total_expenses' => floatval($totalExpenses),
                    'total_income' => floatval($totalIncome),
                    'net_savings' => floatval($totalIncome - $totalExpenses),
                    'top_expenses' => $expenses->take(3),
                    'category_breakdown' => $categoryBreakdown,
                    'bank_balance' => floatval($setting->user->bankAccounts()->sum('balance')),
                    'fund_balance' => floatval($setting->user->fundSources()->sum('amount')),
                ];

                // Send email
                Mail::to($setting->report_email)->send(
                    new ExpenseReportMail($reportData)
                );

                // Update last_sent_at
                $setting->update(['last_sent_at' => Carbon::now()]);

                $count++;
                $this->info("Report sent successfully to {$setting->user->name} ({$setting->report_email})");
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to send report to user {$setting->user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Report sending completed. Sent: {$count}, Failed: {$failed}");
    }

    /**
     * Get the start date based on frequency
     */
    private function getStartDate(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => Carbon::now()->subDay()->startOfDay(),
            'weekly' => Carbon::now()->subWeek()->startOfDay(),
            'monthly' => Carbon::now()->subMonth()->startOfDay(),
            default => Carbon::now()->subMonth()->startOfDay(),
        };
    }

    /**
     * Get the period label for the email
     */
    private function getPeriodLabel(string $frequency): string
    {
        return match ($frequency) {
            'daily' => 'Yesterday',
            'weekly' => 'Last 7 Days',
            'monthly' => 'Last 30 Days',
            default => 'Last 30 Days',
        };
    }
}
