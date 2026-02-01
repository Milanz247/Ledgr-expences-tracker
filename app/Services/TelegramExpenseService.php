<?php

namespace App\Services;

use App\Models\TelegramBot;
use App\Models\TelegramConversation;
use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramExpenseService
{
    protected $bot;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
    }

    public function handleMessage(array $message)
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        $messageThreadId = $message['message_thread_id'] ?? null;

        Log::info("TelegramService: Processing message. user=$userId thread=$messageThreadId text='$text'");

        // Ensure we are in the correct thread
        // Strict check: both null (Main) or match
        // Note: expense_topic_thread_id might be stored as string, cast to compare.
        $configThreadId = $this->bot->expense_topic_thread_id;
        
        Log::info("TelegramService: Configured Thread ID: '$configThreadId'");

        if ((string)$messageThreadId !== (string)$configThreadId) {
            Log::info("TelegramService: Skipped - Thread ID mismatch. Got '$messageThreadId', Expected '$configThreadId'");
            return;
        }

        // Get or Create Conversation State
        $conversation = TelegramConversation::firstOrCreate(
            ['bot_token' => $this->bot->token, 'chat_id' => $chatId, 'user_id' => $userId],
            ['step' => 'start']
        );

        Log::info("TelegramService: Conversation Step: {$conversation->step}");

        // Cancel command
        if (strtolower($text) === '/cancel') {
            $conversation->delete();
            $this->sendMessage($chatId, "Operation cancelled.", $messageThreadId);
            return;
        }

        switch ($conversation->step) {
            case 'start':
                $this->handleStart($conversation, $text, $chatId, $messageThreadId);
                break;
            case 'awaiting_category':
                $this->handleCategory($conversation, $text, $chatId, $messageThreadId);
                break;
            case 'awaiting_description':
                $this->handleDescription($conversation, $text, $chatId, $messageThreadId);
                break;
        }
    }

    protected function handleStart($conversation, $text, $chatId, $threadId)
    {
        if (is_numeric($text)) {
            $amount = floatval($text);
            
            Log::info("TelegramService: Numeric amount received: $amount");

            // Save amount and move to next step
            $conversation->update([
                'step' => 'awaiting_category',
                'data' => ['amount' => $amount]
            ]);

            // Fetch categories to show as options (limited to top 5 or generic)
            // For now, let's just ask for text, or ideally inline keyboard.
            // Simplified: Just ask for text search of category.
            
            $categories = Category::limit(10)->pluck('name')->toArray();
            $keyboard = ['keyboard' => array_chunk($categories, 2), 'one_time_keyboard' => true, 'resize_keyboard' => true];

            $this->sendMessage($chatId, "Amount: {$amount}\nSelect or type a category:", $threadId, $keyboard);
        } else {
            Log::info("TelegramService: Non-numeric message in start state.");
            // Ignore non-numeric messages in 'start' state to strictly listen for numbers
        }
    }

    protected function handleCategory($conversation, $text, $chatId, $threadId)
    {
        // Try to find category by name
        $category = Category::where('name', 'LIKE', "%{$text}%")->first();

        if (!$category) {
            $this->sendMessage($chatId, "Category not found. Please try again or type /cancel.", $threadId);
            return;
        }

        $data = $conversation->data;
        $data['category_id'] = $category->id;
        $data['category_name'] = $category->name;

        $conversation->update([
            'step' => 'awaiting_description',
            'data' => $data
        ]);

        $this->sendMessage($chatId, "Category: {$category->name}\nEnter a description (or type 'skip'):", $threadId, ['remove_keyboard' => true]);
    }

    protected function handleDescription($conversation, $text, $chatId, $threadId)
    {
        $description = strtolower($text) === 'skip' ? 'Expense via Telegram' : $text;

        $data = $conversation->data;
        $amount = $data['amount'];
        $categoryId = $data['category_id'];

        // Create Expense
        try {
            // Find user from telegram mapping? 
            // Currently assuming Single User Bot or using the User who owns the Bot linked (first one)
            // Ideally we need to map Telegram User ID to App User ID.
            // For now, hardcode to the user who connected the bot (TelegramBot doesn't have user_id yet, assume First User or implement user mapping).
            // Simplification: Assign to first user in system for now (since single user app largely).
            $user = \App\Models\User::first();
            
            Expense::create([
                'user_id' => $user->id,
                'category_id' => $categoryId,
                'amount' => $amount,
                'description' => $description,
                'date' => now(),
                'payment_source_id' => $this->bot->default_payment_source_id,
                'payment_source_type' => $this->bot->default_payment_source_type,
            ]);

            // Decrement Balance of Payment Source
            if ($this->bot->default_payment_source_type === 'bank') {
                $account = \App\Models\BankAccount::find($this->bot->default_payment_source_id);
                if ($account) $account->decrement('balance', $amount);
            } elseif ($this->bot->default_payment_source_type === 'fund') {
                $fund = \App\Models\FundSource::find($this->bot->default_payment_source_id);
                if ($fund) $fund->decrement('amount', $amount);
            }
             // Handle 'loan' logic if needed

            $this->sendMessage($chatId, "✅ Expense Saved!\nAmount: {$amount}\nCategory: {$data['category_name']}\nDesc: {$description}", $threadId);
            
            // Clear conversation
            $conversation->delete();

        } catch (\Exception $e) {
            Log::error("Expense Save Error: " . $e->getMessage());
            $this->sendMessage($chatId, "Error saving expense. Please try again.", $threadId);
            $conversation->delete();
        }
    }

    protected function sendMessage($chatId, $text, $threadId, $replyMarkup = [])
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'message_thread_id' => $threadId,
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = $replyMarkup;
        }

        Http::post("https://api.telegram.org/bot{$this->bot->token}/sendMessage", $payload);
    }
}
