<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\FundSourceController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentSourceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Password Reset routes
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('/resend-otp', [PasswordResetController::class, 'resendOtp']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Profile routes
    Route::put('/profile/info', [ProfileController::class, 'updateInfo']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Reports
    Route::get('/reports/expenses-by-category', [ReportController::class, 'expensesByCategory']);
    Route::get('/reports/expenses-by-category-month', [ReportController::class, 'expensesByCategoryMonth']);

    // Report Settings
    Route::get('/report-settings', [\App\Http\Controllers\Api\ReportSettingsController::class, 'getSettings']);
    Route::post('/report-settings', [\App\Http\Controllers\Api\ReportSettingsController::class, 'updateSettings']);
    Route::post('/report-settings/send-test', [\App\Http\Controllers\Api\ReportSettingsController::class, 'sendTestEmail']);

    // Categories - Everyone can view, but only authenticated users can manage
    Route::apiResource('categories', CategoryController::class);

    // Bank Accounts
    Route::apiResource('bank-accounts', BankAccountController::class);

    // Fund Sources
    Route::apiResource('fund-sources', FundSourceController::class);

    // Expenses
    Route::apiResource('expenses', ExpenseController::class);

    // Incomes
    Route::apiResource('incomes', IncomeController::class);

    // Budgets
    Route::get('/budgets-overview', [BudgetController::class, 'overview']);
    Route::post('/budgets-rollover', [BudgetController::class, 'processRollover']);
    Route::apiResource('budgets', BudgetController::class);

    // Recurring Transactions
    Route::get('/recurring-transactions-upcoming', [RecurringTransactionController::class, 'upcomingBills']);
    Route::post('/recurring-transactions/{recurringTransaction}/toggle', [RecurringTransactionController::class, 'toggleActive']);
    Route::apiResource('recurring-transactions', RecurringTransactionController::class);

    // Loans
    Route::apiResource('loans', LoanController::class);
    Route::post('/loans/{loan}/repay', [LoanController::class, 'repay']);
    Route::get('/loans/{loan}/repayments', [LoanController::class, 'repayments']);
    Route::get('/loans-stats', [LoanController::class, 'stats']);

    // Payment Sources (merged: banks, funds, loans)
    Route::get('/payment-sources', [PaymentSourceController::class, 'index']);
});
