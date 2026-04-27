<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'ra_t_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'data',
        'lue',
        'lue_at',
        'priorite',
        'icon',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'lue' => 'boolean',
            'lue_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
