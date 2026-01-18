<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentSourceController extends Controller
{
    /**
     * Get all available payment sources (banks, funds, loans)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $paymentSources = [];

        // Add Bank Accounts
        $bankAccounts = $user->bankAccounts()->get()->map(function ($account) {
            return [
                'id' => $account->id,
                'type' => 'bank',
                'name' => $account->bank_name,
                'balance' => $account->balance,
                'display_name' => "{$account->bank_name} - " . number_format($account->balance, 2),
            ];
        });

        // Add Fund Sources
        $fundSources = $user->fundSources()->get()->map(function ($fund) {
            return [
                'id' => $fund->id,
                'type' => 'fund',
                'name' => $fund->source_name,
                'balance' => $fund->amount,
                'display_name' => "{$fund->source_name} - " . number_format($fund->amount, 2),
            ];
        });

        // Add Loans (only those marked as funding source with available balance)
        $loans = $user->loans()
            ->where('is_funding_source', true)
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->get()
            ->map(function ($loan) {
                $availableBalance = $loan->available_balance;
                if ($availableBalance > 0) {
                    return [
                        'id' => $loan->id,
                        'type' => 'loan',
                        'name' => $loan->lender_name,
                        'balance' => $availableBalance,
                        'original_amount' => $loan->amount,
                        'display_name' => "Loan from {$loan->lender_name} - Rem: " . number_format($availableBalance, 2),
                    ];
                }
                return null;
            })
            ->filter(); // Remove null entries

        // Merge all sources
        $paymentSources = [
            'banks' => $bankAccounts->values(),
            'funds' => $fundSources->values(),
            'loans' => $loans->values(),
            'all' => $bankAccounts->concat($fundSources)->concat($loans)->values(),
        ];

        return response()->json($paymentSources);
    }
}
