<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('bot_token')->index(); // To know which bot this conversation belongs to
            $table->string('chat_id');
            $table->string('user_id'); // Telegram User ID
            $table->string('step'); // Current step: 'awaiting_category', 'awaiting_description'
            $table->json('data')->nullable(); // Temp data: {'amount': 100, 'category_id': 5}
            $table->timestamps();

            $table->unique(['bot_token', 'chat_id', 'user_id']); // One active conversation per user per chat per bot
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_conversations');
    }
};
