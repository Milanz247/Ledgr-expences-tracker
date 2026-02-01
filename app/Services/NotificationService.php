<?php

namespace App\Services;

use App\Models\NotificationRule;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Trigger notifications for a specific event
     */
    public function trigger(int $userId, string $eventType, array $data): void
    {
        // Find all active rules for this event type and user
        $rules = NotificationRule::where('user_id', $userId)
            ->forEvent($eventType)
            ->immediate()
            ->active()
            ->get();

        foreach ($rules as $rule) {
            try {
                // Evaluate conditions
                if (!$this->evaluateConditions($rule, $data)) {
                    continue;
                }

                // Send notification
                $this->send($rule, $data);
            } catch (\Exception $e) {
                Log::error("Notification rule {$rule->id} failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Evaluate if data matches rule conditions
     */
    public function evaluateConditions(NotificationRule $rule, array $data): bool
    {
        if (empty($rule->conditions)) {
            return true; // No conditions = always trigger
        }

        $conditions = $rule->conditions;

        // Amount conditions
        if (isset($conditions['amount_min']) && ($data['amount'] ?? 0) < $conditions['amount_min']) {
            return false;
        }

        if (isset($conditions['amount_max']) && ($data['amount'] ?? 0) > $conditions['amount_max']) {
            return false;
        }

        // Category filter (for expenses/incomes)
        if (isset($conditions['category_id']) && ($data['category_id'] ?? null) != $conditions['category_id']) {
            return false;
        }

        // Category filter - multiple categories
        if (isset($conditions['category_ids']) && !empty($conditions['category_ids'])) {
            if (!in_array($data['category_id'] ?? null, $conditions['category_ids'])) {
                return false;
            }
        }

        // Payment source filter
        if (isset($conditions['payment_source']) && ($data['payment_source'] ?? null) != $conditions['payment_source']) {
            return false;
        }

        return true;
    }

    /**
     * Send notification based on rule configuration
     */
    public function send(NotificationRule $rule, array $data): void
    {
        if ($rule->delivery_channel === 'telegram') {
            $this->sendTelegram($rule, $data);
        }
        // Future: email, SMS, push notifications
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegram(NotificationRule $rule, array $data): void
    {
        $bot = TelegramBot::first();

        if (!$bot || !$bot->chat_id || !$bot->token) {
            Log::warning("Telegram bot not configured");
            return;
        }

        if (!$rule->telegram_topic_id) {
            Log::warning("No topic ID configured for rule {$rule->id}");
            return;
        }

        // Format message
        $message = $this->formatMessage($rule, $data);

        // Send to Telegram
        $response = Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
            'chat_id' => $bot->chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'message_thread_id' => $rule->telegram_topic_id
        ]);

        if (!$response->successful()) {
            Log::error("Telegram send failed for rule {$rule->id}: " . $response->body());
        }
    }

    /**
     * Format message using template and data
     */
    private function formatMessage(NotificationRule $rule, array $data): string
    {
        if ($rule->message_template) {
            // Use custom template with variable replacement
            return $this->replaceVariables($rule->message_template, $data);
        }

        // Default message format based on event type
        return $this->getDefaultMessage($rule->event_type, $data);
    }

    /**
     * Replace variables in template
     */
    private function replaceVariables(string $template, array $data): string
    {
        $variables = [
            '{amount}' => number_format($data['amount'] ?? 0, 2),
            '{description}' => $data['description'] ?? '',
            '{category}' => $data['category']['name'] ?? 'N/A',
            '{date}' => $data['date'] ?? now()->format('Y-m-d'),
            '{payment_source}' => $data['payment_source'] ?? 'N/A',
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    /**
     * Get default message for event type
     */
    private function getDefaultMessage(string $eventType, array $data): string
    {
        return match($eventType) {
            'expense_created' => "💸 *New Expense Created*\n\n" .
                                 "Amount: " . number_format($data['amount'] ?? 0, 2) . "\n" .
                                 "Category: " . ($data['category']['name'] ?? 'N/A') . "\n" .
                                 "Description: " . ($data['description'] ?? 'N/A') . "\n" .
                                 "Date: " . ($data['date'] ?? now()->format('Y-m-d')),

            'income_created' => "💰 *New Income Received*\n\n" .
                                "Amount: " . number_format($data['amount'] ?? 0, 2) . "\n" .
                                "Category: " . ($data['category']['name'] ?? 'N/A') . "\n" .
                                "Description: " . ($data['description'] ?? 'N/A') . "\n" .
                                "Date: " . ($data['date'] ?? now()->format('Y-m-d')),

            'loan_created' => "💳 *New Loan Created*\n\n" .
                              "Amount: " . number_format($data['amount'] ?? 0, 2) . "\n" .
                              "Description: " . ($data['description'] ?? 'N/A'),

            'budget_exceeded' => "🚨 *Critical Budget Alert*\n\n" .
                                 "Category: " . ($data['category_name'] ?? 'N/A') . "\n" .
                                 "Budget: " . number_format($data['budget_amount'] ?? 0, 2) . "\n" .
                                 "Spent: " . number_format($data['spent_amount'] ?? 0, 2) . "\n" .
                                 "Status: 🛑 OVER BUDGET!",

            'budget_warning' => "⚠️ *Budget Warning*\n\n" .
                                "Category: " . ($data['category_name'] ?? 'N/A') . "\n" .
                                "Budget: " . number_format($data['budget_amount'] ?? 0, 2) . "\n" .
                                "Spent: " . number_format($data['spent_amount'] ?? 0, 2) . "\n" .
                                "Used: " . ($data['percentage'] ?? 0) . "%",

            default => "📬 *Notification*\n\n" . json_encode($data, JSON_PRETTY_PRINT)
        };
    }

    /**
     * Process scheduled notifications (for command)
     */
    public function processScheduled(): void
    {
        $now = now('Asia/Colombo');

        $rules = NotificationRule::scheduled()
            ->active()
            ->whereNotNull('schedule_time')
            ->get();

        foreach ($rules as $rule) {
            if ($this->shouldSendScheduled($rule, $now)) {
                try {
                    // Generate summary data based on schedule frequency
                    $data = $this->generateSummaryData($rule);
                    $this->send($rule, $data);
                } catch (\Exception $e) {
                    Log::error("Scheduled notification {$rule->id} failed: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check if scheduled notification should be sent now
     */
    private function shouldSendScheduled(NotificationRule $rule, $now): bool
    {
        $scheduleTime = \Carbon\Carbon::parse($rule->schedule_time);
        
        if ($now->format('H:i') !== $scheduleTime->format('H:i')) {
            return false;
        }

        switch ($rule->schedule_frequency) {
            case 'daily':
                return true;
                
            case 'weekly':
                return $now->format('l') === $rule->schedule_day;
                
            case 'monthly':
                return $now->day == intval($rule->schedule_day);
                
            default:
                return false;
        }
    }

    /**
     * Generate summary data for scheduled reports
     */
    private function generateSummaryData(NotificationRule $rule): array
    {
        // Get date range based on frequency
        [$startDate, $endDate] = $this->getDateRange($rule->schedule_frequency);

        $data = [];

        // Calculate totals based on event type
        if (str_contains($rule->event_type, 'summary')) {
            $data['total_expenses'] = \DB::table('expenses')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $data['total_income'] = \DB::table('incomes')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $data['balance'] = $data['total_income'] - $data['total_expenses'];
            $data['period'] = $this->getPeriodLabel($rule->schedule_frequency);
        }

        return $data;
    }

    /**
     * Get date range for summary
     */
    private function getDateRange(string $frequency): array
    {
        $now = now('Asia/Colombo');

        return match($frequency) {
            'daily' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            ],
            'weekly' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek()
            ],
            'monthly' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ],
            default => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            ]
        };
    }

    /**
     * Get period label
     */
    private function getPeriodLabel(string $frequency): string
    {
        return match($frequency) {
            'daily' => 'Today',
            'weekly' => 'This Week',
            'monthly' => 'This Month',
            default => 'Today'
        };
    }
}
