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
        Schema::table('report_settings', function (Blueprint $table) {
            $table->time('daily_report_time')->nullable()->after('frequency');
            $table->string('telegram_topic_id')->nullable()->after('daily_report_time');
            $table->string('telegram_chat_id')->nullable()->after('telegram_topic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_report_time', 'telegram_topic_id', 'telegram_chat_id']);
        });
    }
};
