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
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->boolean('notify_expenses')->default(false)->after('topic_data');
            $table->string('expense_topic_id')->nullable()->after('notify_expenses');
            $table->boolean('daily_summary')->default(false)->after('expense_topic_id');
            $table->time('daily_summary_time')->default('20:00')->after('daily_summary');
            $table->string('summary_topic_id')->nullable()->after('daily_summary_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn([
                'notify_expenses',
                'expense_topic_id',
                'daily_summary',
                'daily_summary_time',
                'summary_topic_id'
            ]);
        });
    }
};
