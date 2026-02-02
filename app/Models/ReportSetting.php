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
        'report_email', // Keeping for backward compat, though we might not use it
        'frequency',
        'daily_report_time',
        'telegram_topic_id',
        'telegram_chat_id',
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
