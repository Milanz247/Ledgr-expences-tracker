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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('fund_source_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name'); // e.g., "Netflix Subscription"
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->integer('day_of_month')->nullable(); // For monthly: 1-31
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable(); // For weekly
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Optional end date
            $table->date('next_due_date'); // Calculated next occurrence
            $table->date('last_processed_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_3_days_before')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'next_due_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
