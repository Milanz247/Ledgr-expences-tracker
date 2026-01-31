<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use Illuminate\Http\Request;

class FundSourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $fundSources = $request->user()->fundSources()->latest()->get();
        return response()->json(['data' => $fundSources]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return response()->json(['message' => 'Creating new fund sources is not allowed.'], 403);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, FundSource $fundSource)
    {
        // Ensure user can only view their own fund sources
        if ($fundSource->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($fundSource);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FundSource $fundSource)
    {
        return response()->json(['message' => 'Updating fund sources is not allowed.'], 403);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, FundSource $fundSource)
    {
        return response()->json(['message' => 'Deleting fund sources is not allowed.'], 403);
    }

    /**
     * Withdraw from Bank Account to Wallet
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = $request->user();
        $bankAccount = $user->bankAccounts()->findOrFail($request->bank_account_id);
        $wallet = $user->fundSources()->firstOrFail(); // Default wallet

        if ($bankAccount->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient funds in bank account.'], 422);
        }

        try {
            \DB::transaction(function () use ($bankAccount, $wallet, $request) {
                // Deduct from bank
                $bankAccount->decrement('balance', $request->amount);

                // Add to wallet
                $wallet->increment('amount', $request->amount);
            });

            return response()->json([
                'message' => 'Withdrawal successful',
                'wallet_balance' => $wallet->amount,
                'bank_balance' => $bankAccount->balance,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Withdrawal failed.'], 500);
        }
    }
}
