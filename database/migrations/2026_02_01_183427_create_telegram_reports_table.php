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
        Schema::create('telegram_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained('telegram_bots')->onDelete('cascade');
            $table->string('title');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly']);
            $table->time('time');
            $table->string('day')->nullable(); // e.g., 'Monday', '1'
            $table->json('content'); // e.g., ['expenses', 'incomes']
            $table->string('topic_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_reports');
    }
};
