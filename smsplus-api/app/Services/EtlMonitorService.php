<?php

namespace App\Services;

use App\Models\EtlJob;
use Illuminate\Support\Facades\Request;

class EtlMonitorService
{
    /**
     * Normalise les compteurs (total_rows / processed_rows / error_rows) depuis metadata.
     * Retourne toujours un tableau avec les clés si elles peuvent être déduites.
     */
    public function normalizeRowCounts(array $metadata, ?EtlJob $job = null): array
    {
        $normalized = [];

        if (isset($metadata['total_rows'])) {
            $normalized['total_rows'] = (int) $metadata['total_rows'];
        } elseif (isset($metadata['nb_lignes'])) {
            $normalized['total_rows'] = (int) $metadata['nb_lignes'];
        }

        if (isset($metadata['processed_rows'])) {
            $normalized['processed_rows'] = (int) $metadata['processed_rows'];
        } elseif (isset($metadata['rows_processed'])) {
            $normalized['processed_rows'] = (int) $metadata['rows_processed'];
        } elseif (isset($metadata['rows_inserted'])) {
            $normalized['processed_rows'] = (int) $metadata['rows_inserted'];
        } elseif (isset($metadata['nb_resultats'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_resultats'];
        } elseif (isset($metadata['nb_reclamations'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_reclamations'];
        } elseif (isset($metadata['nb_services'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_services'];
        } elseif (isset($metadata['nb_alertes'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_alertes'];
        } elseif (isset($metadata['nb_transactions'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_transactions'];
        } elseif (isset($metadata['nb_points'])) {
            $normalized['processed_rows'] = (int) $metadata['nb_points'];
        } elseif (isset($metadata['count'])) {
            $normalized['processed_rows'] = (int) $metadata['count'];
        }

        if (isset($metadata['error_rows'])) {
            $normalized['error_rows'] = (int) $metadata['error_rows'];
        } elseif (isset($metadata['rows_skipped'])) {
            $normalized['error_rows'] = (int) $metadata['rows_skipped'];
        }

        // Cas particulier: job système/commande/rapport réussis mais sans volume.
        if (
            $job &&
            ($normalized['processed_rows'] ?? null) === null &&
            ($normalized['total_rows'] ?? null) === null
        ) {
            if (in_array($job->category, ['systeme', 'command', 'rapport'], true)) {
                $normalized['processed_rows'] = 1;
            }
        }

        return $normalized;
    }


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
            
            $normalized = $this->normalizeRowCounts($metadata, $status === 'success' ? $job : null);
            if (isset($normalized['total_rows'])) {
                $updateData['total_rows'] = $normalized['total_rows'];
            }
            if (isset($normalized['processed_rows'])) {
                $updateData['processed_rows'] = $normalized['processed_rows'];
            }
            if (isset($normalized['error_rows'])) {
                $updateData['error_rows'] = $normalized['error_rows'];
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
            
            $normalized = $this->normalizeRowCounts($metadata);
            if (isset($normalized['total_rows'])) {
                $updateData['total_rows'] = $normalized['total_rows'];
            }
            if (isset($normalized['processed_rows'])) {
                $updateData['processed_rows'] = $normalized['processed_rows'];
            }
            if (isset($normalized['error_rows'])) {
                $updateData['error_rows'] = $normalized['error_rows'];
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
