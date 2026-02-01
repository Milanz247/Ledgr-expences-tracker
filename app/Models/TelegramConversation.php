<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramConversation extends Model
{
    protected $fillable = [
        'bot_token',
        'chat_id',
        'user_id',
        'step',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
