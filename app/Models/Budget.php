<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'spent',
        'rollover_amount',
        'rollover_enabled',
        'month',
        'year',
        'alert_at_90_percent',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent' => 'decimal:2',
        'rollover_amount' => 'decimal:2',
        'rollover_enabled' => 'boolean',
        'alert_at_90_percent' => 'boolean',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id', 'category_id')
                    ->where('user_id', $this->user_id);
    }

    // Calculate total available budget (including rollover)
    public function getTotalBudgetAttribute(): float
    {
        return floatval($this->amount) + floatval($this->rollover_amount);
    }

    // Calculate remaining budget
    public function getRemainingAttribute(): float
    {
        return $this->total_budget - floatval($this->spent);
    }

    // Calculate percentage used
    public function getPercentageUsedAttribute(): float
    {
        if ($this->total_budget <= 0) return 0;
        return (floatval($this->spent) / $this->total_budget) * 100;
    }

    // Check if budget exceeded 90%
    public function isNearLimitAttribute(): bool
    {
        return $this->percentage_used >= 90;
    }

    // Check if budget exceeded
    public function isExceededAttribute(): bool
    {
        return floatval($this->spent) > $this->total_budget;
    }
}
