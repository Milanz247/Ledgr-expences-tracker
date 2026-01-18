<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $bankAccounts = $request->user()->bankAccounts;
        return response()->json(['data' => $bankAccounts]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        $bankAccount = $request->user()->bankAccounts()->create($request->all());

        return response()->json($bankAccount, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, BankAccount $bankAccount)
    {
        // Ensure user can only view their own bank accounts
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($bankAccount);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        // Ensure user can only update their own bank accounts
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'bank_name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:255',
            'balance' => 'sometimes|required|numeric|min:0',
        ]);

        $bankAccount->update($request->all());

        return response()->json($bankAccount);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, BankAccount $bankAccount)
    {
        // Ensure user can only delete their own bank accounts
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if there are any expenses linked to this bank account
        if ($bankAccount->expenses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete. This account has transaction history.'
            ], 422);
        }

        $bankAccount->delete();

        return response()->json([
            'message' => 'Bank account deleted successfully',
        ]);
    }
}
