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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('account_holder_name')->nullable()->after('user_id');
            $table->string('branch_code')->nullable()->after('account_number');
            $table->string('color')->nullable()->after('balance'); // For future card customization
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_holder_name', 'branch_code', 'color']);
        });
    }
};
