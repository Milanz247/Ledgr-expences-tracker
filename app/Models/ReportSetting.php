<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_email',
        'frequency',
        'is_enabled',
        'last_sent_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Get the user that owns the report setting.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
