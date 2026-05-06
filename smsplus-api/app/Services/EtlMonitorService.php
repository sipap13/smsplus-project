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
            
            // Update row counts using correct column names
            if (isset($metadata['total_rows'])) {
                $updateData['total_rows'] = $metadata['total_rows'];
            } elseif (isset($metadata['nb_lignes'])) {
                $updateData['total_rows'] = $metadata['nb_lignes'];
            }

            if (isset($metadata['processed_rows'])) {
                $updateData['processed_rows'] = $metadata['processed_rows'];
            } elseif (isset($metadata['rows_processed'])) {
                $updateData['processed_rows'] = $metadata['rows_processed'];
            } elseif (isset($metadata['rows_inserted'])) {
                $updateData['processed_rows'] = $metadata['rows_inserted'];
            } elseif (isset($metadata['nb_resultats'])) {
                $updateData['processed_rows'] = $metadata['nb_resultats'];
            } elseif (isset($metadata['nb_reclamations'])) {
                $updateData['processed_rows'] = $metadata['nb_reclamations'];
            } elseif (isset($metadata['nb_services'])) {
                $updateData['processed_rows'] = $metadata['nb_services'];
            } elseif (isset($metadata['nb_alertes'])) {
                $updateData['processed_rows'] = $metadata['nb_alertes'];
            } elseif (isset($metadata['nb_transactions'])) {
                $updateData['processed_rows'] = $metadata['nb_transactions'];
            } elseif (isset($metadata['nb_points'])) {
                $updateData['processed_rows'] = $metadata['nb_points'];
            } elseif (isset($metadata['count'])) {
                $updateData['processed_rows'] = $metadata['count'];
            }

            if (isset($metadata['error_rows'])) {
                $updateData['error_rows'] = $metadata['error_rows'];
            } elseif (isset($metadata['rows_skipped'])) {
                $updateData['error_rows'] = $metadata['rows_skipped'];
            }

            // Si c'est un job système/commande/rapport réussi mais sans volume, on met 1 par défaut
            if ($status === 'success' && empty($updateData['processed_rows']) && empty($updateData['total_rows'])) {
                if (in_array($job->category, ['systeme', 'command', 'rapport'])) {
                    $updateData['processed_rows'] = 1;
                }
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
            $updateData['metadata'] = array_merge((array) ($job->metadata ?? []), $metadata);
            
            if (isset($metadata['total_rows'])) {
                $updateData['total_rows'] = $metadata['total_rows'];
            }
            if (isset($metadata['processed_rows'])) {
                $updateData['processed_rows'] = $metadata['processed_rows'];
            } elseif (isset($metadata['rows_inserted'])) {
                $updateData['processed_rows'] = $metadata['rows_inserted'];
            }
            if (isset($metadata['error_rows'])) {
                $updateData['error_rows'] = $metadata['error_rows'];
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
