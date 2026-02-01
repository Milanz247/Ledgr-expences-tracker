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
     * Get the current connected bot.
     */
    public function getBot()
    {
        $bot = TelegramBot::first();
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

        if ($response->successful()) {
            return response()->json(['message' => 'Topic closed successfully.']);
        }

        return response()->json([
            'message' => 'Failed to close topic: ' . ($response->json()['description'] ?? 'Unknown error'),
        ], 400);
    }

    /**
     * Delete a forum topic.
     */
    public function deleteTopic(Request $request)
    {
        $request->validate([
            'thread_id' => 'required',
            'name' => 'nullable|string', // Pass name to remove from local storage
        ]);

        $bot = TelegramBot::first();
        if (!$bot) return response()->json(['message' => 'Bot not connected.'], 404);

        $threadId = $request->input('thread_id');
        $name = $request->input('name');

        $response = Http::post("https://api.telegram.org/bot{$bot->token}/deleteForumTopic", [
            'chat_id' => $bot->chat_id,
            'message_thread_id' => $threadId,
        ]);

        if ($response->successful()) {
            // Remove from local storage if name is provided
            if ($name) {
                $topicData = $bot->topic_data ?? [];
                if (isset($topicData[$name])) {
                    unset($topicData[$name]);
                    $bot->topic_data = $topicData;
                    $bot->save();
                }
            }

            return response()->json([
                'message' => 'Topic deleted successfully.',
                'data' => $bot
            ]);
        }

        return response()->json([
            'message' => 'Failed to delete topic: ' . ($response->json()['description'] ?? 'Unknown error'),
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
