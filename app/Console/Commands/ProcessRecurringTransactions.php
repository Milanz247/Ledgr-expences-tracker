<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecurringTransaction;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recurring transactions that are due today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing recurring transactions...');

        $dueTransactions = RecurringTransaction::where('is_active', true)
            ->whereDate('next_due_date', '<=', Carbon::today())
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($dueTransactions as $recurring) {
            try {
                DB::beginTransaction();

                // Create expense from recurring transaction
                Expense::create([
                    'user_id' => $recurring->user_id,
                    'category_id' => $recurring->category_id,
                    'bank_account_id' => $recurring->bank_account_id,
                    'fund_source_id' => $recurring->fund_source_id,
                    'amount' => $recurring->amount,
                    'description' => $recurring->description ?? "Recurring: {$recurring->name}",
                    'date' => $recurring->next_due_date,
                ]);

                // Update budget spent if exists
                $month = $recurring->next_due_date->month;
                $year = $recurring->next_due_date->year;

                $budget = $recurring->user->budgets()
                    ->where('category_id', $recurring->category_id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($budget) {
                    $budget->spent += $recurring->amount;
                    $budget->save();
                }

                // Deduct from bank account or fund source
                if ($recurring->bank_account_id) {
                    $bankAccount = $recurring->bankAccount;
                    $bankAccount->balance -= $recurring->amount;
                    $bankAccount->save();
                } elseif ($recurring->fund_source_id) {
                    $fundSource = $recurring->fundSource;
                    $fundSource->amount -= $recurring->amount;
                    $fundSource->save();
                }

                // Update recurring transaction
                $recurring->last_processed_date = $recurring->next_due_date;
                $recurring->next_due_date = $recurring->calculateNextDueDate();

                // Check if recurring should end
                if ($recurring->end_date && $recurring->next_due_date->greaterThan($recurring->end_date)) {
                    $recurring->is_active = false;
                }

                $recurring->save();

                DB::commit();
                $processed++;

                $this->info("Processed: {$recurring->name} - {$recurring->amount}");

            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $this->error("Failed to process: {$recurring->name} - " . $e->getMessage());
            }
        }

        $this->info("Processed: {$processed}, Failed: {$failed}");
        return 0;
    }
}

