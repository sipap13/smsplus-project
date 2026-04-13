<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reclamation extends Model
{
    protected $table = 'ra_t_reclamations';

    protected $fillable = [
        'msisdn',
        'service_id',
        'description',
        'statut',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
