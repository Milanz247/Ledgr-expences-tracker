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
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('item_name');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('monthly_amount', 15, 2);
            $table->integer('total_months');
            $table->integer('paid_months')->default(0);
            $table->date('start_date');
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            // Optional links to payment source for auto-created expenses
            $table->foreignId('bank_account_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('fund_source_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
