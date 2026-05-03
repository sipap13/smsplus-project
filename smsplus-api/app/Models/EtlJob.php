<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class EtlJob extends Model
{
    use HasFactory;

    protected $table = 'ra_t_etl_jobs';

    protected $fillable = [
        'job_name',
        'category',
        'source',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'total_rows',
        'processed_rows',
        'error_rows',
        'error_message',
        'metadata',
        'metrics',
        'triggered_by',
        'page',
        'user_email',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => AsArrayObject::class,
        'metrics' => AsArrayObject::class,
    ];

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByJobName($query, string $jobName)
    {
        return $query->where('job_name', $jobName);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function getDurationInSecondsAttribute(): float
    {
        return $this->duration_ms / 1000;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return min(100, ($this->processed_rows / $this->total_rows) * 100);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['success', 'failed']);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
