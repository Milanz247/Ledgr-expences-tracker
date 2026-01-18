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
        Schema::table('expenses', function (Blueprint $table) {
            // Make bank_account_id nullable
            $table->foreignId('bank_account_id')->nullable()->change();

            // Add fund_source_id as nullable foreign key
            $table->foreignId('fund_source_id')->nullable()->after('bank_account_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['fund_source_id']);
            $table->dropColumn('fund_source_id');

            // Make bank_account_id required again
            $table->foreignId('bank_account_id')->nullable(false)->change();
        });
    }
};
