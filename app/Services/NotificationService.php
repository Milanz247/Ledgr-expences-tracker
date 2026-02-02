<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Trigger a notification event
     *
     * @param int $userId
     * @param string $event
     * @param array $data
     * @return void
     */
    public function trigger($userId, $string, $data)
    {
        try {
            switch ($string) {
                case 'income_created':
                    $this->telegramService->sendIncomeNotification($data);
                    break;
                case 'expense_created':
                    // Expense controller currently calls TelegramService directly,
                    // but we can support it here for future refactoring
                    $this->telegramService->sendExpenseNotification($data);
                    break;
                default:
                    Log::info("Notification event [$string] triggered but not handled.");
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Failed to trigger notification for event [$string]: " . $e->getMessage());
        }
    }
}
