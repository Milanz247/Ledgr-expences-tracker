<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $fillable = [
        'token',
        'chat_id',
        'topic_data',
        'expense_topic_thread_id',
        'default_payment_source_id',
        'default_payment_source_type',
    ];

    protected $casts = [
        'topic_data' => 'array',
    ];
}
