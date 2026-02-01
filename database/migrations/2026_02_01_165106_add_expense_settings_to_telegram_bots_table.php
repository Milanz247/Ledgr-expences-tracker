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
            $table->string('expense_topic_thread_id')->nullable()->after('topic_data');
            $table->string('default_payment_source_id')->nullable()->after('expense_topic_thread_id');
            $table->string('default_payment_source_type')->nullable()->after('default_payment_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn([
                'expense_topic_thread_id',
                'default_payment_source_id',
                'default_payment_source_type'
            ]);
        });
    }
};
