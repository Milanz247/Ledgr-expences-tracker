<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change event_type from ENUM to STRING to allow flexibility
        DB::statement("ALTER TABLE notification_rules MODIFY COLUMN event_type VARCHAR(255)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to ENUM (Warning: Data might be truncated if it contains non-enum values)
        // We probably shouldn't revert strictly but for completeness:
        // DB::statement("ALTER TABLE notification_rules MODIFY COLUMN event_type ENUM('expense_created','income_created','loan_created','loan_payment_due','daily_summary','weekly_summary','monthly_summary')");
    }
};
