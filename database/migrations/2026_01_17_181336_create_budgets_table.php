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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2); // Monthly budget limit
            $table->decimal('spent', 15, 2)->default(0); // Current month spent
            $table->decimal('rollover_amount', 15, 2)->default(0); // Carried over from previous month
            $table->boolean('rollover_enabled')->default(false);
            $table->integer('month'); // 1-12
            $table->integer('year'); // e.g., 2026
            $table->boolean('alert_at_90_percent')->default(true);
            $table->timestamps();

            // Unique constraint: one budget per user, category, month, year
            $table->unique(['user_id', 'category_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
