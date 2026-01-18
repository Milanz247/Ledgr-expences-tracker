<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ExpenseReportMail;
use App\Models\ReportSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReportSettingsController extends Controller
{
    /**
     * Get report settings for authenticated user
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();
        $settings = $user->reportSettings()->first();

        if (!$settings) {
            $settings = ReportSetting::create([
                'user_id' => $user->id,
                'report_email' => $user->email,
                'frequency' => 'weekly',
                'is_enabled' => false,
            ]);
        }

        return response()->json($settings);
    }

    /**
     * Save or update report settings
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'report_email' => 'required|email',
            'frequency' => 'required|in:daily,weekly,monthly',
            'is_enabled' => 'required|boolean',
        ]);

        $settings = $user->reportSettings()->firstOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $settings->update($validated);

        return response()->json([
            'message' => 'Report settings updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Send a test email with the current report
     */
    public function sendTestEmail(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'report_email' => 'required|email',
        ]);

        try {
            // Get expenses for the last 30 days (for test purposes)
            $expenses = $user->expenses()
                ->with(['category', 'bankAccount', 'fundSource'])
                ->where('date', '>=', Carbon::now()->subDays(30))
                ->orderByDesc('amount')
                ->get();

            // Get category breakdown
            $categoryBreakdown = $user->expenses()
                ->select('category_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
                ->with('category')
                ->where('date', '>=', Carbon::now()->subDays(30))
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
            $totalExpenses = $user->expenses()
                ->where('date', '>=', Carbon::now()->subDays(30))
                ->sum('amount');

            $totalIncome = $user->fundSources()
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->sum('amount');

            $reportData = [
                'user_name' => $user->name,
                'period' => 'Last 30 Days (Test)',
                'total_expenses' => floatval($totalExpenses),
                'total_income' => floatval($totalIncome),
                'net_savings' => floatval($totalIncome - $totalExpenses),
                'top_expenses' => $expenses->take(3),
                'category_breakdown' => $categoryBreakdown,
                'bank_balance' => floatval($user->bankAccounts()->sum('balance')),
                'fund_balance' => floatval($user->fundSources()->sum('amount')),
            ];

            Mail::to($validated['report_email'])->send(
                new ExpenseReportMail($reportData)
            );

            return response()->json([
                'message' => 'Test email sent successfully to ' . $validated['report_email'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
