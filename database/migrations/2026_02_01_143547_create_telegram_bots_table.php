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
        Schema::create('telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('chat_id')->nullable();
            $table->json('topic_data')->nullable();
            $table->boolean('notify_expenses')->default(false);
            $table->string('expense_topic_id')->nullable();
            $table->boolean('daily_summary')->default(false);
            $table->time('daily_summary_time')->default('20:00');
            $table->string('summary_topic_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bots');
    }
};
