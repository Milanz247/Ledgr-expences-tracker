<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'lender_name',
        'amount',
        'balance_remaining',
        'description',
        'status',
        'due_date',
        'is_funding_source',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'due_date' => 'date',
        'is_funding_source' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    /**
     * Calculate the available balance for this loan (if used as funding source)
     * Available balance = Original Amount - Sum of expenses using this loan
     */
    public function getAvailableBalanceAttribute()
    {
        $totalSpent = $this->expenses()->sum('amount');
        return $this->amount - $totalSpent;
    }

    /**
     * Update loan balance and status after repayment
     */
    public function processRepayment($amount)
    {
        $this->balance_remaining -= $amount;

        if ($this->balance_remaining <= 0) {
            $this->balance_remaining = 0;
            $this->status = 'paid';
        } elseif ($this->balance_remaining < $this->amount) {
            $this->status = 'partially_paid';
        }

        $this->save();
    }

    /**
     * Calculate percentage paid
     */
    public function getPercentagePaidAttribute()
    {
        if ($this->amount <= 0) {
            return 0;
        }

        $amountPaid = $this->amount - $this->balance_remaining;
        return round(($amountPaid / $this->amount) * 100, 2);
    }
}
