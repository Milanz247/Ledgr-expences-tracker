<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $fillable = [
        'token',
        'chat_id',
        'topic_data',
    ];

    protected $casts = [
        'topic_data' => 'array',
    ];
}
