<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'event_type',
        'conditions',
        'delivery_channel',
        'telegram_topic_id',
        'message_template',
        'is_active',
        'schedule_time',
        'schedule_frequency',
        'schedule_day',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'schedule_time' => 'datetime:H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeImmediate($query)
    {
        return $query->where('schedule_frequency', 'immediate');
    }

    public function scopeScheduled($query)
    {
        return $query->whereIn('schedule_frequency', ['daily', 'weekly', 'monthly']);
    }
}
