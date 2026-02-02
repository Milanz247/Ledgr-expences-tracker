<?php

namespace App\Services;

use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send expense notification if enabled
     */
    public function sendExpenseNotification(array $expenseData): void
    {
        try {
            $bot = TelegramBot::first();

            // Check if bot is connected and expense notifications are enabled
            if (!$bot || !$bot->token || !$bot->chat_id || !$bot->notify_expenses) {
                return;
            }

            // Check if topic is configured (optional, but good practice if they use topics)
            $threadId = $bot->expense_topic_id;

            $message = $this->formatExpenseMessage($expenseData);

            $this->sendMessage($bot, $message, $threadId);

        } catch (\Exception $e) {
            Log::error("Telegram notification failed: " . $e->getMessage());
        }
    }

    /**
     * Send generic message
     */
    private function sendMessage($bot, string $message, ?string $threadId = null): void
    {
        $payload = [
            'chat_id' => $bot->chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ];

        if ($threadId) {
            $payload['message_thread_id'] = $threadId;
        }

        Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", $payload);
    }

    /**
     * Format expense message
     */
    private function formatExpenseMessage(array $data): string
    {
        $amount = number_format($data['amount'] ?? 0, 2);
        $category = $data['category']['name'] ?? 'N/A';
        $description = $data['description'] ?? 'N/A';
        $date = $data['date'] ?? now()->format('Y-m-d');
        
        return "💸 *New Expense Created*\n\n" .
               "Amount: {$amount}\n" .
               "Category: {$category}\n" .
               "Description: {$description}\n" .
               "Date: {$date}";
    }

    /**
     * Send income notification
     */
    public function sendIncomeNotification(array $incomeData): void
    {
        try {
            $bot = TelegramBot::first();

            // Check if bot is connected (we can reuse expense flag or assume general notifications for now)
            // Ideally we'd have a separate 'notify_incomes' flag, but for now let's use the bot existence
            if (!$bot || !$bot->token || !$bot->chat_id) {
                return;
            }

            // Use the same topic or main chat
            $threadId = $bot->expense_topic_id; // Or a new income_topic_id if available

            $message = $this->formatIncomeMessage($incomeData);

            $this->sendMessage($bot, $message, $threadId);

        } catch (\Exception $e) {
            Log::error("Telegram income notification failed: " . $e->getMessage());
        }
    }

    /**
     * Format income message
     */
    private function formatIncomeMessage(array $data): string
    {
        $amount = number_format($data['amount'] ?? 0, 2);
        $category = $data['category']['name'] ?? 'N/A';
        $description = $data['description'] ?? 'N/A';
        $date = $data['date'] ?? now()->format('Y-m-d');
        
        return "💰 *New Income Recorded*\n\n" .
               "Amount: {$amount}\n" .
               "Category: {$category}\n" .
               "Description: {$description}\n" .
               "Date: {$date}";
    }
}
