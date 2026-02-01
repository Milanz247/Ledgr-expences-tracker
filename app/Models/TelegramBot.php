<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $fillable = [
        'token',
        'chat_id',
        'topic_data',
        'notify_expenses',
        'expense_topic_id',
        'daily_summary',
        'daily_summary_time',
        'summary_topic_id',
    ];

    protected $casts = [
        'topic_data' => 'array',
    ];
}
