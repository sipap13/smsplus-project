<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'ra_t_audit_logs';

    public $timestamps = false; // Using custom created_at

    protected $fillable = [
        'user_id',
        'user_email',
        'user_role',
        'action',
        'entite',
        'entite_id',
        'description',
        'donnees_avant',
        'donnees_apres',
        'ip_address',
        'user_agent',
        'statut',
        'created_at'
    ];

    protected $casts = [
        'donnees_avant' => 'array',
        'donnees_apres' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
