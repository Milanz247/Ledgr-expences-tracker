<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'sidebar_settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sidebar_settings' => 'array',
        ];
    }

    /**
     * Get the bank accounts for the user.
     */
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the fund sources for the user.
     */
    public function fundSources()
    {
        return $this->hasMany(FundSource::class);
    }

    /**
     * Get the expenses for the user.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the loans for the user.
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Get the categories for the user.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the incomes for the user.
     */
    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Get the budgets for the user.
     */
    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get the recurring transactions for the user.
     */
    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * Get the report settings for the user.
     */
    public function reportSettings()
    {
        return $this->hasOne(ReportSetting::class);
    }

    /**
     * Get the installments for the user.
     */
    public function installments()
    {
        return $this->hasMany(Installment::class);
    }
}
