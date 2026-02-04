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
            $table->boolean('monthly_summary')->default(false)->after('summary_topic_id');
            $table->tinyInteger('monthly_summary_day')->default(1)->after('monthly_summary'); // Day of month (1-28)
            $table->time('monthly_summary_time')->default('09:00')->after('monthly_summary_day');
            $table->string('monthly_summary_topic_id')->nullable()->after('monthly_summary_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_summary',
                'monthly_summary_day',
                'monthly_summary_time',
                'monthly_summary_topic_id'
            ]);
        });
    }
};
