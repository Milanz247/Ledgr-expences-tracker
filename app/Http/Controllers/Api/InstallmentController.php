<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $installments = Installment::where('user_id', $request->user()->id)
            ->with(['category', 'bankAccount', 'fundSource'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $installments]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'monthly_amount' => 'required|numeric|min:0',
            'total_months' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'category_id' => 'required|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['paid_months'] = 0;
        $validated['status'] = 'ongoing';

        $installment = Installment::create($validated);

        return response()->json(['message' => 'Installment plan created successfully', 'data' => $installment], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Installment $installment)
    {
        if ($installment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'item_name' => 'sometimes|required|string|max:255',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'monthly_amount' => 'sometimes|required|numeric|min:0',
            'total_months' => 'sometimes|required|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'category_id' => 'sometimes|required|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'fund_source_id' => 'nullable|exists:fund_sources,id',
        ]);

        $installment->update($validated);

        return response()->json(['message' => 'Installment plan updated successfully', 'data' => $installment]);
    }

    /**
     * Pay the monthly installment.
     */
    public function pay(Request $request, Installment $installment)
    {
        if ($installment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($installment->status === 'completed') {
            return response()->json(['message' => 'Installment already completed'], 400);
        }

        $request->validate([
            'months_to_pay' => 'nullable|integer|min:1',
        ]);

        $monthsToPay = $request->input('months_to_pay', 1);
        
        // Ensure we don't pay more than remaining months
        $remainingMonths = $installment->total_months - $installment->paid_months;
        if ($monthsToPay > $remainingMonths) {
            return response()->json(['message' => "Cannot pay more than remaining months ($remainingMonths)"], 422);
        }

        $totalToPay = $installment->monthly_amount * $monthsToPay;

        try {
            DB::transaction(function () use ($installment, $request, $monthsToPay, $totalToPay) {
                // 1. Balance Validation & Deduction
                if ($installment->bank_account_id) {
                    $bankAccount = $installment->bankAccount;
                    if ($bankAccount->balance < $totalToPay) {
                        throw new \Exception('Insufficient balance in selected bank account', 422);
                    }
                    $bankAccount->decrement('balance', $totalToPay);
                } elseif ($installment->fund_source_id) {
                    $fundSource = $installment->fundSource;
                    if ($fundSource->amount < $totalToPay) {
                        throw new \Exception('Insufficient balance in selected fund source', 422);
                    }
                    $fundSource->decrement('amount', $totalToPay);
                }

                // 2. Create Single Expense Record
                Expense::create([
                    'user_id' => $request->user()->id,
                    'category_id' => $installment->category_id,
                    'amount' => $totalToPay,
                    'description' => 'Installment: ' . $installment->item_name . ' (' . $monthsToPay . ' months)',
                    'date' => now(),
                    'bank_account_id' => $installment->bank_account_id,
                    'fund_source_id' => $installment->fund_source_id,
                ]);

                // 3. Update Installment Progress
                $installment->increment('paid_months', $monthsToPay);

                // 4. Check for completion
                if ($installment->paid_months >= $installment->total_months) {
                    $installment->update(['status' => 'completed']);
                }
            });

            return response()->json([
                'message' => 'Installment paid successfully',
                'data' => $installment->fresh(['category', 'bankAccount', 'fundSource'])
            ]);
        } catch (\Exception $e) {
            $status = $e->getCode() === 422 ? 422 : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Installment $installment)
    {
        if ($installment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $installment->delete();

        return response()->json(['message' => 'Installment plan deleted successfully']);
    }
}
