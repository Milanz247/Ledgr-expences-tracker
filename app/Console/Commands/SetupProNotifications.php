<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\NotificationRule;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;

class SetupProNotifications extends Command
{
    protected $signature = 'notifications:setup-pro';
    protected $description = 'Set up professional notification rules and Telegram topics';

    public function handle()
    {
        $this->info('Starting Pro Notification Setup...');

        $user = User::first();
        if (!$user) {
            $this->error('No user found.');
            return;
        }

        $bot = TelegramBot::first();
        if (!$bot || !$bot->token || !$bot->chat_id) {
            $this->error('Telegram Bot not connected. Please connect it in Settings first.');
            return;
        }

        // 1. Setup Topics
        $requiredTopics = ['Alerts', 'Reports', 'Activity'];
        $topicIds = $this->ensureTopicsExist($bot, $requiredTopics);

        // 2. Create Rules
        $this->createRules($user, $topicIds);

        $this->info('✅ Pro Notification Setup Complete!');
        $this->info('Topics created: ' . implode(', ', array_keys($topicIds)));
        $this->info('Rules configured for User: ' . $user->name);
    }

    private function ensureTopicsExist($bot, $topics)
    {
        $currentData = $bot->topic_data ?? [];
        $ids = [];

        foreach ($topics as $name) {
            if (isset($currentData[$name])) {
                $ids[$name] = $currentData[$name];
                $this->line("Topic '$name' already exists (ID: {$ids[$name]})");
            } else {
                $this->line("Creating topic '$name'...");
                try {
                    $response = Http::post("https://api.telegram.org/bot{$bot->token}/createForumTopic", [
                        'chat_id' => $bot->chat_id,
                        'name' => $name
                    ]);

                    if ($response->successful()) {
                        $threadId = $response->json()['result']['message_thread_id'];
                        $ids[$name] = $threadId;
                        $currentData[$name] = $threadId;
                        $this->info("Created '$name' (ID: $threadId)");
                    } else {
                        $this->error("Failed to create '$name': " . $response->body());
                        // Fallback to General topic (null/0) or skip? Skip for now.
                    }
                } catch (\Exception $e) {
                    $this->error("Error creating '$name': " . $e->getMessage());
                }
            }
        }

        // Save updated topic data
        $bot->topic_data = $currentData;
        $bot->save();

        return $ids;
    }

    private function createRules($user, $topicIds)
    {
        $this->info('Configuring Notification Rules...');

        // Clear existing rules to avoid duplicates? Or just create if not exists?
        // Let's safe-delete by name prefix or just create new ones. 
        // Better to allow user to have multiple. But preventing duplicates is nice.
        // We'll delete rules with exact same names we are about to create.

        $rules = [
            [
                'name' => '🚨 High Expense Alert',
                'event_type' => 'expense_created',
                'conditions' => ['amount_min' => 5000],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Alerts'] ?? null,
                'message_template' => "⚠️ *High Expense Detected!*\n\nAmount: {amount}\nCategory: {category}\nDescription: {description}\n\nPlease verify this transaction.",
                'schedule_frequency' => 'immediate'
            ],
            [
                'name' => '🛑 Budget Critical Alert',
                'event_type' => 'budget_exceeded',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Alerts'] ?? null,
                'schedule_frequency' => 'immediate'
            ],
            [
                'name' => '⚠️ Budget Warning',
                'event_type' => 'budget_warning',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Alerts'] ?? null,
                'schedule_frequency' => 'immediate'
            ],
            [
                'name' => '💰 Income Received',
                'event_type' => 'income_created',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Activity'] ?? null,
                'message_template' => "🤑 *Income Received*\n\nAmount: {amount}\nSource: {description}\n\nTime to save!",
                'schedule_frequency' => 'immediate'
            ],
            [
                'name' => '🏦 New Loan Alert',
                'event_type' => 'loan_created',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Alerts'] ?? null,
                'schedule_frequency' => 'immediate'
            ],
            [
                'name' => '📊 Daily Snapshot',
                'event_type' => 'daily_summary',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Reports'] ?? null,
                'schedule_frequency' => 'daily',
                'schedule_time' => '20:00'
            ],
            [
                'name' => '📈 Weekly Review',
                'event_type' => 'weekly_summary',
                'conditions' => [],
                'delivery_channel' => 'telegram',
                'telegram_topic_id' => $topicIds['Reports'] ?? null,
                'schedule_frequency' => 'weekly',
                'schedule_day' => 'Friday',
                'schedule_time' => '20:00'
            ]
        ];

        foreach ($rules as $ruleData) {
            if (empty($ruleData['telegram_topic_id'])) {
                $this->warn("Skipping rule '{$ruleData['name']}' - Topic not found.");
                continue;
            }

            NotificationRule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $ruleData['name']
                ],
                array_merge($ruleData, ['is_active' => true])
            );
            $this->line("Rule '{$ruleData['name']}' configured.");
        }
    }
}
