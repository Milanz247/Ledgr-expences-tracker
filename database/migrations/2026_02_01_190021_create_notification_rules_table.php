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
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('event_type', [
                'expense_created',
                'income_created', 
                'loan_created',
                'loan_payment_due',
                'daily_summary',
                'weekly_summary',
                'monthly_summary'
            ]);
            $table->json('conditions')->nullable(); // Amount, category filters, etc.
            $table->enum('delivery_channel', ['telegram'])->default('telegram');
            $table->string('telegram_topic_id')->nullable();
            $table->text('message_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->time('schedule_time')->nullable();
            $table->enum('schedule_frequency', ['immediate', 'daily', 'weekly', 'monthly'])->default('immediate');
            $table->string('schedule_day')->nullable(); // Day of week or month
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
