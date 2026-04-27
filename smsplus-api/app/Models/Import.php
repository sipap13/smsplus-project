<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $table = 'ra_t_imports';

    protected $fillable = [
        'filename',
        'type',
        'status',
        'total_rows',
        'imported_rows',
        'error_rows',
        'error_message',
        'imported_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
