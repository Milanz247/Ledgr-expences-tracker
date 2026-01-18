<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ExportController extends Controller
{
    /**
     * Export transactions (Income & Expense) as CSV
     */
    public function exportTransactions(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $fileName = 'Ledgr_Report_' . date('Y-m-d_H-i') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($user, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');

            // Header Row
            fputcsv($file, ['Date', 'Type', 'Category', 'Description', 'Amount', 'Source', 'Status']);

            // Fetch Expenses
            $expensesQuery = $user->expenses()->with(['category', 'bankAccount', 'fundSource']);
            if ($fromDate) $expensesQuery->whereDate('date', '>=', $fromDate);
            if ($toDate) $expensesQuery->whereDate('date', '<=', $toDate);
            $expenses = $expensesQuery->get();

            foreach ($expenses as $expense) {
                // Determine source name
                $source = 'Unknown';
                if ($expense->bank_account_id) {
                    $source = $expense->bankAccount ? $expense->bankAccount->bank_name : 'Bank Account';
                } elseif ($expense->fund_source_id) {
                    $source = $expense->fundSource ? $expense->fundSource->source_name : 'Cash/Wallet';
                } elseif ($expense->loan_id) {
                    $source = 'Loan'; 
                }

                fputcsv($file, [
                    $expense->date,
                    'Expense',
                    $expense->category ? $expense->category->name : 'Uncategorized',
                    $expense->description ?? '',
                    '-' . $expense->amount, // Negative for expenses
                    $source,
                    'Completed'
                ]);
            }

            // Fetch Incomes
            $incomesQuery = $user->incomes()->with(['category', 'bankAccount', 'fundSource']);
            if ($fromDate) $incomesQuery->whereDate('date', '>=', $fromDate);
            if ($toDate) $incomesQuery->whereDate('date', '<=', $toDate);
            $incomes = $incomesQuery->get();

            foreach ($incomes as $income) {
                // Determine destination (source in this context)
                $source = 'Unknown';
                if ($income->bank_account_id) {
                    $source = $income->bankAccount ? $income->bankAccount->bank_name : 'Bank Account';
                } elseif ($income->fund_source_id) {
                    $source = $income->fundSource ? $income->fundSource->source_name : 'Cash/Wallet';
                }

                fputcsv($file, [
                    $income->date,
                    'Income',
                    $income->category ? $income->category->name : 'Uncategorized',
                    $income->description ?? '',
                    $income->amount,
                    $source,
                    'Received'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
