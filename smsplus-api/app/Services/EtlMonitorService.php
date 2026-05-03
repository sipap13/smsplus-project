<?php

namespace App\Services;

use App\Models\EtlJob;
use Illuminate\Support\Facades\Request;

class EtlMonitorService
{
    public function startJob(string $jobName, string $category = 'command', ?string $source = null, int $totalLignes = 0, array $metadata = []): EtlJob
    {
        $page = $metadata['page'] ?? null;
        $triggeredBy = $metadata['triggered_by'] ?? $this->getTriggeredBy();
        
        $job = EtlJob::create([
            'job_name' => $jobName,
            'category' => $category,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata,
            'page' => $page,
        ]);

        return $job;
    }

    public function finishJob(EtlJob $job, string $status = 'success', ?string $errorMessage = null, array $metadata = []): EtlJob
    {
        $updateData = [
            'status' => $status,
            'finished_at' => now(),
            'duration_ms' => (int) $job->started_at->diffInMilliseconds(now()),
        ];
        
        if ($errorMessage) {
            $updateData['error_message'] = $errorMessage;
        }
        
        if (!empty($metadata)) {
            $updateData['metadata'] = array_merge((array) ($job->metadata ?? []), $metadata);
            
            // Update row counts if provided
            if (isset($metadata['nb_lignes'])) {
                $updateData['rows_processed'] = $metadata['nb_lignes'];
            } elseif (isset($metadata['rows_processed'])) {
                $updateData['rows_processed'] = $metadata['rows_processed'];
            }
            
            if (isset($metadata['nb_resultats'])) {
                $updateData['rows_inserted'] = $metadata['nb_resultats'];
            }
        }
        
        $job->update($updateData);

        return $job;
    }

    public function failJob(EtlJob $job, string $errorMessage): EtlJob
    {
        $job->update([
            'status' => 'failed',
            'finished_at' => now(),
            'duration_ms' => (int) $job->started_at->diffInMilliseconds(now()),
            'error_message' => $errorMessage,
        ]);

        return $job;
    }

    public function updateJob(EtlJob $job, array $metadata = []): EtlJob
    {
        $updateData = [];
        
        if (!empty($metadata)) {
            $updateData['metadata'] = array_merge($job->metadata ?? [], $metadata);
            
            // Update row counts if provided
            if (isset($metadata['rows_processed'])) {
                $updateData['rows_processed'] = $metadata['rows_processed'];
            }
            
            if (isset($metadata['rows_inserted'])) {
                $updateData['rows_inserted'] = $metadata['rows_inserted'];
            }
        }
        
        if (!empty($updateData)) {
            $job->update($updateData);
        }

        return $job;
    }

    private function getTriggeredBy(): string
    {
        if (app()->runningInConsole()) {
            return 'auto';
        }

        return 'user';
    }

    private function getCurrentPage(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::header('X-Current-Page') ?: null;
    }
}
