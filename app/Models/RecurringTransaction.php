<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'bank_account_id',
        'fund_source_id',
        'name',
        'description',
        'amount',
        'frequency',
        'day_of_month',
        'day_of_week',
        'start_date',
        'end_date',
        'next_due_date',
        'last_processed_date',
        'is_active',
        'notify_3_days_before',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_due_date' => 'date',
        'last_processed_date' => 'date',
        'is_active' => 'boolean',
        'notify_3_days_before' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }

    // Calculate next due date based on frequency
    public function calculateNextDueDate(): Carbon
    {
        $current = $this->next_due_date ?? $this->start_date;
        $date = Carbon::parse($current);

        switch ($this->frequency) {
            case 'daily':
                return $date->addDay();
            case 'weekly':
                return $date->addWeek();
            case 'monthly':
                return $date->addMonth();
            case 'yearly':
                return $date->addYear();
            default:
                return $date;
        }
    }

    // Check if notification should be sent
    public function shouldNotify(): bool
    {
        if (!$this->notify_3_days_before || !$this->is_active) {
            return false;
        }

        $notifyDate = Carbon::parse($this->next_due_date)->subDays(3);
        return Carbon::today()->isSameDay($notifyDate);
    }

    // Check if transaction is due today
    public function isDueToday(): bool
    {
        return $this->is_active && Carbon::today()->isSameDay($this->next_due_date);
    }
}
