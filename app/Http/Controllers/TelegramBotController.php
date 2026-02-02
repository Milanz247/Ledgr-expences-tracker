<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{

    /**
     * Connect the bot by validating the token and saving it.
     */
    public function connectBot(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');

        try {
            // Attempt to get updates to find the Chat ID
            $chatId = $this->getChatIdFromTelegram($token);

            // Save or update the bot configuration
            $bot = TelegramBot::updateOrCreate(
                ['token' => $token],
                ['chat_id' => $chatId]
            );

            return response()->json([
                'message' => 'Bot connected successfully.',
                'data' => $bot,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create default topics in the group.
     */
    public function createTopics(Request $request)
    {
        $request->validate([
            'token' => 'required|string', // Or retrieve by ID if preferred
        ]);

        $token = $request->input('token');
        $bot = TelegramBot::where('token', $token)->first();

        if (!$bot || !$bot->chat_id) {
            return response()->json([
                'message' => 'Bot not found or Chat ID missing.',
            ], 404);
        }

        // Topics to create
        $topics = ['General', 'Alerts', 'Reports']; // Customize as needed
        $topicData = $bot->topic_data ?? [];

        $results = [];

        foreach ($topics as $topic) {
            // Check if topic already exists in our records to avoid duplicates (optional logic)
            // For now, we'll try to create it or skip if we handled that logic
            
            $response = Http::post("https://api.telegram.org/bot{$token}/createForumTopic", [
                'chat_id' => $bot->chat_id,
                'name'    => $topic
            ]);

            if ($response->successful()) {
                $threadId = $response->json()['result']['message_thread_id'];
                $topicData[$topic] = $threadId;
                $results[] = "Created topic: $topic";
            } else {
                // Handle duplicate or error
                $error = $response->json()['description'] ?? 'Unknown error';
                $results[] = "Failed to create topic $topic: $error";
            }
        }

        // Save updated topic data
        $bot->topic_data = $topicData;
        $bot->save();

        return response()->json([
            'message' => 'Topics creation process completed.',
            'results' => $results,
            'topic_data' => $topicData,
        ]);
    }

    /**
     * Update bot settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'notify_expenses' => 'boolean',
            'expense_topic_id' => 'nullable|string',
            'daily_summary' => 'boolean',
            'daily_summary_time' => 'nullable|date_format:H:i',
            'summary_topic_id' => 'nullable|string',
        ]);

        $bot = TelegramBot::first();
        if (!$bot) {
            return response()->json(['message' => 'Bot not connected.'], 404);
        }

        $bot->update($request->only([
            'notify_expenses', 
            'expense_topic_id', 
            'daily_summary', 
            'daily_summary_time', 
            'summary_topic_id'
        ]));

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $bot,
        ]);
    }

    /**
     * Get the current connected bot.
     */
    public function getBot()
    {
        $bot = TelegramBot::first();
        
        if ($bot && $bot->topic_data) {
            // Transform topic_data to forum_topics format for frontend
            $forumTopics = [];
            foreach ($bot->topic_data as $name => $threadId) {
                $forumTopics[] = [
                    'name' => $name,
                    'message_thread_id' => $threadId
                ];
            }
            $bot->forum_topics = $forumTopics;
        }
        
        return response()->json([
            'data' => $bot,
        ]);
    }

    /**
     * Disconnect the bot (delete configuration).
     */
    public function disconnectBot()
    {
        TelegramBot::truncate(); // Or delete specific if multiple
        return response()->json([
            'message' => 'Bot disconnected successfully.',
        ]);
    }

    /**
     * Create a single topic manually.
     */
    public function createSingleTopic(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:64',
        ]);

        $bot = TelegramBot::first();
        if (!$bot || !$bot->chat_id) {
            return response()->json(['message' => 'Bot not connected.'], 404);
        }

        $name = $request->input('name');
        
        $response = Http::post("https://api.telegram.org/bot{$bot->token}/createForumTopic", [
            'chat_id' => $bot->chat_id,
            'name'    => $name,
        ]);

        if ($response->successful()) {
            $threadId = $response->json()['result']['message_thread_id'];
            
            // Update topic data
            $topicData = $bot->topic_data ?? [];
            $topicData[$name] = $threadId;
            $bot->topic_data = $topicData;
            $bot->save();

            // Transform to forum_topics format for response
            $forumTopics = [];
            foreach ($topicData as $topicName => $topicThreadId) {
                $forumTopics[] = [
                    'name' => $topicName,
                    'message_thread_id' => $topicThreadId
                ];
            }
            $bot->forum_topics = $forumTopics;

            return response()->json([
                'message' => "Topic '$name' created successfully.",
                'data' => $bot,
                'thread_id' => $threadId
            ]);
        }

        return response()->json([
            'message' => 'Failed to create topic: ' . ($response->json()['description'] ?? 'Unknown error'),
        ], 400);
    }

    /**
     * Close a forum topic.
     */
    public function closeTopic(Request $request)
    {
        $request->validate([
            'thread_id' => 'required',
        ]);

        $bot = TelegramBot::first();
        if (!$bot) return response()->json(['message' => 'Bot not connected.'], 404);

        $threadId = $request->input('thread_id');

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/closeForumTopic", [
            'chat_id' => $bot->chat_id,
            'message_thread_id' => $threadId,
        ]);

        $responseData = $response->json();
        $description = $responseData['description'] ?? '';

        if ($response->successful() || str_contains($description, 'TOPIC_NOT_MODIFIED')) {
            return response()->json(['message' => 'Topic closed successfully.']);
        }

        return response()->json([
            'message' => 'Failed to close topic: ' . ($description ?? 'Unknown error'),
        ], 400);
    }

    /**
     * Delete a forum topic.
     */
    public function deleteTopic(Request $request)
    {
        $request->validate([
            'thread_id' => 'required',
            'name' => 'nullable',
            'force_local' => 'nullable',
        ]);

        $bot = TelegramBot::first();
        if (!$bot) return response()->json(['message' => 'Bot not connected.'], 404);

        $threadId = $request->input('thread_id');
        $name = $request->input('name');
        $forceLocal = $request->boolean('force_local', false);

        Log::info("Attempting to delete topic", [
            'thread_id' => $threadId,
            'name' => $name,
            'chat_id' => $bot->chat_id,
            'force_local' => $forceLocal
        ]);

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/deleteForumTopic", [
            'chat_id' => $bot->chat_id,
            'message_thread_id' => $threadId,
        ]);

        $responseData = $response->json();
        Log::info("Telegram deleteForumTopic response", ['response' => $responseData]);

        $telegramSuccess = $response->successful() && ($responseData['ok'] ?? false);
        
        // Remove from local storage if Telegram succeeded OR force_local is true
        if ($telegramSuccess || $forceLocal) {
            $topicData = $bot->topic_data ?? [];
            $removed = false;
            
            if ($name && isset($topicData[$name])) {
                unset($topicData[$name]);
                $removed = true;
            } else {
                // Try to find and remove by thread_id
                foreach ($topicData as $topicName => $topicThreadId) {
                    if ($topicThreadId == $threadId) {
                        unset($topicData[$topicName]);
                        $removed = true;
                        break;
                    }
                }
            }
            
            if ($removed) {
                $bot->topic_data = $topicData;
                $bot->save();
            }

            // Transform to forum_topics format for response
            $forumTopics = [];
            foreach ($topicData as $topicName => $topicThreadId) {
                $forumTopics[] = [
                    'name' => $topicName,
                    'message_thread_id' => $topicThreadId
                ];
            }
            $bot->forum_topics = $forumTopics;

            $message = $telegramSuccess 
                ? 'Topic deleted successfully.' 
                : 'Topic removed from local storage (Telegram deletion failed: ' . ($responseData['description'] ?? 'Unknown') . ')';

            return response()->json([
                'message' => $message,
                'data' => $bot
            ]);
        }

        $errorMessage = $responseData['description'] ?? 'Unknown error';
        Log::error("Failed to delete topic", ['error' => $errorMessage, 'response' => $responseData]);

        return response()->json([
            'message' => 'Failed to delete topic: ' . $errorMessage,
            'error_details' => $errorMessage
        ], 400);
    }

    /**
     * Helper to get Chat ID from getUpdates
     */
    private function getChatIdFromTelegram($token)
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates");
            
            if ($response->failed()) {
                Log::error("Telegram getUpdates failed: " . $response->body());
                if ($response->status() === 401) {
                    throw new \Exception("Invalid Bot Token.");
                }
                throw new \Exception("Telegram API Error: " . $response->status());
            }

            $data = $response->json();

            if (empty($data['result'])) {
                throw new \Exception("No updates found. Please ensure the bot is added to the group and you've sent a 'Hi' message recently.");
            }

            // Get the last update
            $lastUpdate = end($data['result']);

            // Logic to find chat_id from message or my_chat_member
            $chatId = $lastUpdate['message']['chat']['id'] 
                   ?? $lastUpdate['my_chat_member']['chat']['id'] 
                   ?? null;

            if (!$chatId) {
                throw new \Exception("Could not extract Chat ID from the last update.");
            }

            return $chatId;

        } catch (\Exception $e) {
            Log::error("Exception in getChatIdFromTelegram: " . $e->getMessage());
            throw $e;
        }
    }
}
