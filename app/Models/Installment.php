<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_name',
        'total_amount',
        'monthly_amount',
        'total_months',
        'paid_months',
        'start_date',
        'status',
        'bank_account_id',
        'fund_source_id',
        'category_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'monthly_amount' => 'decimal:2',
        'start_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
