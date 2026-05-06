<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiProviderService;
use App\Services\ChatbotService;
use App\Services\StatisticalPredictor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PredictionController extends Controller
{
    public function __construct(
        protected ChatbotService      $chatbotService,
        protected AiProviderService   $aiProvider,
        protected StatisticalPredictor $statisticalPredictor
    ) {}

    public function revenus(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:64'],
            'subscriber_type' => ['nullable', 'string', 'max:32'],
            'horizon' => ['nullable', 'integer', 'in:7,14,30'],
            'granularite' => ['nullable', 'in:jour,semaine,mois'],
            'nocache' => ['nullable'],
        ]);

        $keyword = $validated['keyword'] ?? null;
        $subscriberType = $validated['subscriber_type'] ?? null;
        $horizon = (int) ($validated['horizon'] ?? 7);
        $granularite = $validated['granularite'] ?? 'jour';
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);

        // Clé cache basée sur l'heure (Y-m-d-H)
        $cacheKey = 'predictions_' . $horizon . '_' . ($keyword ?? 'all') . '_' . ($subscriberType ?? 'all') . '_' . date('Y-m-d-H');
        $cacheDuration = 7200; // 2 heures

        if ($bypassCache) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_time');
        }

        $startTime = microtime(true);
        $this->logJobProgress('prediction_data_collect', 'running');

        $historiqueRaw = DB::table('ra_t_occ_cdr_detail')
            ->selectRaw("
                start_date::date as start_date,
                keyword,
                subscriber_type,
                SUM(charge_amount) as total_revenus,
                COUNT(*) as nb_cdr,
                COUNT(DISTINCT a_msisdn) as nb_abonnes,
                AVG(charge_amount) as revenu_moyen,
                MAX(charge_amount) as revenu_max,
                MIN(charge_amount) as revenu_min
            ")
            ->where('call_type', '=', 'VAS')
            ->whereRaw("start_date >= NOW() - INTERVAL '60 days'")
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('keyword', '=', $keyword);
            })
            ->when($subscriberType, function ($query) use ($subscriberType) {
                $query->where('subscriber_type', '=', $subscriberType);
            })
            ->groupByRaw('start_date::date, keyword, subscriber_type')
            ->orderByRaw('start_date::date asc')
            ->get()
            ->map(function ($row) {
                return [
                    'start_date'     => (string) $row->start_date,
                    'keyword'        => (string) ($row->keyword ?? 'AUTRE'),
                    'subscriber_type'=> (string) ($row->subscriber_type ?? 'INCONNU'),
                    'total_revenus'  => (float) ($row->total_revenus ?? 0),
                    'nb_cdr'         => (int) ($row->nb_cdr ?? 0),
                    'nb_abonnes'     => (int) ($row->nb_abonnes ?? 0),
                    'revenu_moyen'   => (float) ($row->revenu_moyen ?? 0),
                    'revenu_max'     => (float) ($row->revenu_max ?? 0),
                    'revenu_min'     => (float) ($row->revenu_min ?? 0),
                ];
            })
            ->toArray();

        $this->logJobProgress('prediction_data_collect', 'success', (int)((microtime(true) - $startTime) * 1000));
        $this->logJobProgress('prediction_metrics_calc', 'running');
        $startMetrics = microtime(true);

        $historique = $this->aggregateByGranularity($historiqueRaw, $granularite);

        if (count($historique) < 7) {
            return response()->json([
                'insuffisant'         => true,
                'message'             => 'Donnees insuffisantes pour une prediction fiable (minimum 7 jours requis, actuellement '.count($historique).' jours)',
                'redirect_suggestion' => '/import',
                'historique'          => $historique,
                'predictions'         => [],
                'predictions_par_service' => [],
                'resume_semaine'      => null,
                'analyse_detaillee'   => null,
                'recommandations'     => [],
                'stats'               => $this->computeStats($historique),
                'metriques_avancees'  => null,
                'score_fiabilite'     => 0,
                'ai_provider'         => 'none',
                'ai_model'            => null,
                'ai_fallback'         => false,
            ]);
        }

        $stats    = $this->computeStats($historique);
        $metriques = $this->computeAdvancedMetrics($historique, $historiqueRaw);
        
        $this->logJobProgress('prediction_metrics_calc', 'success', (int)((microtime(true) - $startMetrics) * 1000));

        $cacheHit = Cache::has($cacheKey);
        
        $predictions = Cache::remember($cacheKey, $cacheDuration, function () use ($historique, $stats, $metriques, $horizon, $cacheKey, $cacheDuration) {
            $this->logJobProgress('prediction_groq_call', 'running');
            $startAi = microtime(true);

            $systemPrompt = 'Tu es un expert senior en analyse financiere telecom chez Tunisie Telecom, specialise en services VAS SMS+. Tu reponds UNIQUEMENT en JSON valide strict, sans markdown, sans texte hors JSON.';
            $userPrompt   = $this->buildPrompt($historique, $stats, $metriques, $horizon);

            $aiResult = $this->aiProvider->complete($systemPrompt, $userPrompt, 3000, 0.3, $historique);
            
            if ($aiResult['provider'] === 'php_fallback') {
                $data = $this->statisticalPredictor->predict($historique, $horizon);
                $data['provider_original'] = 'php_fallback';
                $data['ai_model'] = 'statistical_model_v2';
            } else {
                $data = $this->parseAiResponse($aiResult['content']);
                if (!$data) {
                    $data = $this->statisticalPredictor->predict($historique, $horizon);
                    $data['provider_original'] = 'php_fallback';
                    $data['ai_model'] = 'statistical_model_v2';
                } else {
                    $data['provider_original'] = $aiResult['provider'];
                    $data['ai_model'] = $aiResult['model'];
                }
            }

            $data['ai_provider'] = $aiResult['provider'];
            $data['ai_fallback'] = $aiResult['fallback'];
            
            $this->logJobProgress('prediction_groq_call', 'success', (int)((microtime(true) - $startAi) * 1000));
            $this->logJobProgress('prediction_cache_save', 'running');

            // Ajustement du score
            $data = $this->adjustScoreByProvider($data, $aiResult['provider'], count($historique));
            
            // Sauvegarde du timestamp
            Cache::put($cacheKey . '_time', now(), $cacheDuration);
            
            $this->logJobProgress('prediction_cache_save', 'success');

            return $data;
        });

        return response()->json([
            'historique'              => $historique,
            'predictions'             => $predictions['predictions_journalieres'] ?? [],
            'predictions_par_service' => $predictions['predictions_par_service']  ?? [],
            'resume_semaine'          => $predictions['resume_semaine']            ?? null,
            'analyse_detaillee'       => $predictions['analyse_detaillee']         ?? null,
            'recommandations'         => $predictions['recommandations']           ?? [],
            'score_fiabilite'         => $predictions['score_fiabilite']           ?? 0,
            'methodologie'            => $predictions['methodologie']              ?? '',
            'stats'                   => $stats,
            'metriques_avancees'      => $metriques,
            'source'                  => $predictions['provider_original'] ?? $predictions['ai_provider'],
            'ai_provider'             => $predictions['ai_provider'],
            'ai_model'                => $predictions['ai_model'],
            'ai_fallback'             => $predictions['ai_fallback'],
            'cache_hit'               => $cacheHit,
            'cached_at'               => Cache::get($cacheKey . '_time'),
            'provider_original'       => $predictions['provider_original'] ?? $predictions['ai_provider'],
            'params' => [
                'keyword'        => $keyword,
                'subscriber_type'=> $subscriberType,
                'horizon'        => $horizon,
                'granularite'    => $granularite,
            ],
            'cache' => [
                'cache_hit'   => $cacheHit,
                'cached_at'   => Cache::get($cacheKey . '_time'),
                'next_refresh'=> $cacheHit ? null : now()->addSeconds($cacheDuration)->toIso8601String(),
            ],
        ]);
    }

    private function logJobProgress(string $jobName, string $status, ?int $duration = null, ?array $metadata = null)
    {
        try {
            DB::table('ra_t_etl_jobs')->updateOrInsert(
                ['job_name' => $jobName],
                [
                    'status' => $status,
                    'started_at' => now(),
                    'finished_at' => $status === 'success' ? now() : null,
                    'duration_ms' => $duration,
                    'metadata' => $metadata ? json_encode($metadata) : null,
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            // silent fail
        }
    }

    protected function aggregateByGranularity(array $rows, string $granularite): array
    {
        if ($granularite === 'jour' || empty($rows)) {
            return $this->rollupByDate($rows);
        }

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['start_date'];
            if ($granularite === 'semaine') {
                $key = date('Y-W', strtotime($date));
            } else {
                $key = date('Y-m', strtotime($date));
            }
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'start_date' => $key,
                    'total_revenus' => 0,
                    'nb_cdr' => 0,
                    'nb_abonnes' => 0,
                    'revenu_moyen_sum' => 0,
                    'revenu_max' => 0,
                    'revenu_min' => null,
                    'count' => 0,
                ];
            }
            $g = &$grouped[$key];
            $g['total_revenus'] += (float) ($row['total_revenus'] ?? 0);
            $g['nb_cdr'] += (int) ($row['nb_cdr'] ?? 0);
            $g['nb_abonnes'] += (int) ($row['nb_abonnes'] ?? 0);
            $g['revenu_moyen_sum'] += (float) ($row['revenu_moyen'] ?? 0);
            $g['revenu_max'] = max($g['revenu_max'], (float) ($row['revenu_max'] ?? 0));
            $min = (float) ($row['revenu_min'] ?? 0);
            $g['revenu_min'] = ($g['revenu_min'] === null) ? $min : min($g['revenu_min'], $min);
            $g['count']++;
        }

        $result = [];
        foreach ($grouped as $g) {
            $result[] = [
                'start_date' => $g['start_date'],
                'total_revenus' => round($g['total_revenus'], 3),
                'nb_cdr' => $g['nb_cdr'],
                'nb_abonnes' => $g['nb_abonnes'],
                'revenu_moyen' => $g['count'] > 0 ? round($g['revenu_moyen_sum'] / $g['count'], 3) : 0,
                'revenu_max' => round($g['revenu_max'], 3),
                'revenu_min' => round($g['revenu_min'] ?? 0, 3),
            ];
        }

        return $result;
    }

    protected function rollupByDate(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $d = $row['start_date'];
            if (! isset($grouped[$d])) {
                $grouped[$d] = [
                    'start_date' => $d,
                    'total_revenus' => 0,
                    'nb_cdr' => 0,
                    'nb_abonnes' => 0,
                    'revenu_moyen' => 0,
                    'revenu_max' => 0,
                    'revenu_min' => null,
                    'count' => 0,
                ];
            }
            $g = &$grouped[$d];
            $g['total_revenus'] += (float) ($row['total_revenus'] ?? 0);
            $g['nb_cdr'] += (int) ($row['nb_cdr'] ?? 0);
            $g['nb_abonnes'] += (int) ($row['nb_abonnes'] ?? 0);
            $g['revenu_moyen'] += (float) ($row['revenu_moyen'] ?? 0);
            $g['revenu_max'] = max($g['revenu_max'], (float) ($row['revenu_max'] ?? 0));
            $min = (float) ($row['revenu_min'] ?? 0);
            $g['revenu_min'] = ($g['revenu_min'] === null) ? $min : min($g['revenu_min'], $min);
            $g['count']++;
        }

        $result = [];
        foreach ($grouped as $g) {
            $result[] = [
                'start_date' => $g['start_date'],
                'total_revenus' => round($g['total_revenus'], 3),
                'nb_cdr' => $g['nb_cdr'],
                'nb_abonnes' => $g['nb_abonnes'],
                'revenu_moyen' => $g['count'] > 0 ? round($g['revenu_moyen'] / $g['count'], 3) : 0,
                'revenu_max' => round($g['revenu_max'], 3),
                'revenu_min' => round($g['revenu_min'] ?? 0, 3),
            ];
        }

        return $result;
    }

    protected function computeStats(array $historique): array
    {
        if (empty($historique)) {
            return [
                'moyenne_journaliere' => 0,
                'tendance_globale' => 'stable',
                'total_60j' => 0,
                'meilleur_jour' => ['date' => null, 'montant' => 0],
                'pire_jour' => ['date' => null, 'montant' => 0],
            ];
        }

        $totals = array_map(fn ($h) => (float) ($h['total_revenus'] ?? 0), $historique);
        $total = array_sum($totals);
        $count = count($totals);
        $moyenne = $total / $count;

        $mid = (int) floor($count / 2);
        $firstHalf = array_slice($totals, 0, $mid);
        $secondHalf = array_slice($totals, $mid);
        $avgFirst = $mid > 0 ? (array_sum($firstHalf) / $mid) : 0;
        $avgSecond = count($secondHalf) > 0 ? (array_sum($secondHalf) / count($secondHalf)) : 0;

        if ($avgSecond > $avgFirst * 1.02) {
            $tendance = 'hausse';
        } elseif ($avgSecond < $avgFirst * 0.98) {
            $tendance = 'baisse';
        } else {
            $tendance = 'stable';
        }

        $maxVal = max($totals);
        $minVal = min($totals);
        $maxIdx = array_search($maxVal, $totals, true);
        $minIdx = array_search($minVal, $totals, true);

        return [
            'moyenne_journaliere' => round($moyenne, 3),
            'tendance_globale' => $tendance,
            'total_60j' => round($total, 3),
            'meilleur_jour' => [
                'date' => $historique[$maxIdx]['start_date'] ?? null,
                'montant' => round($maxVal, 3),
            ],
            'pire_jour' => [
                'date' => $historique[$minIdx]['start_date'] ?? null,
                'montant' => round($minVal, 3),
            ],
        ];
    }

    protected function computeAdvancedMetrics(array $historique, array $raw): array
    {
        $totals = array_map(fn ($h) => (float) ($h['total_revenus'] ?? 0), $historique);
        $count = count($totals);
        if ($count < 2) {
            return [
                'croissance_journaliere_moyenne' => 0,
                'croissance_hebdomadaire' => 0,
                'volatilite' => 0,
                'coefficient_variation' => 0,
                'meilleur_jour_semaine' => null,
                'pire_jour_semaine' => null,
                'semaine_plus_forte' => null,
                'par_service' => [],
                'par_subscriber_type' => [],
                'moyenne_7j' => 0,
                'moyenne_30j' => 0,
                'ecart_type' => 0,
                'tendance_lineaire' => 0,
                'variation_weekend_vs_semaine_pct' => 0,
                'revenus_par_keyword_top5' => [],
            ];
        }

        $growths = [];
        for ($i = 1; $i < $count; $i++) {
            $prev = $totals[$i - 1];
            if ($prev > 0) {
                $growths[] = (($totals[$i] - $prev) / $prev) * 100;
            }
        }
        $croissanceJour = empty($growths) ? 0 : round(array_sum($growths) / count($growths), 2);

        $croissanceSem = 0;
        if ($count >= 8) {
            $last = $totals[$count - 1];
            $prev7 = $totals[$count - 8];
            if ($prev7 > 0) {
                $croissanceSem = round((($last - $prev7) / $prev7) * 100, 2);
            }
        }

        $last7 = array_slice($totals, -7);
        $moyenne7j = count($last7) > 0 ? round(array_sum($last7) / count($last7), 3) : 0;
        $last30 = array_slice($totals, -30);
        $moyenne30j = count($last30) > 0 ? round(array_sum($last30) / count($last30), 3) : 0;

        $moyenne = array_sum($totals) / $count;
        $variance = 0;
        foreach ($totals as $v) {
            $variance += pow($v - $moyenne, 2);
        }
        $stdDev = sqrt($variance / $count);
        $volatilite = $moyenne > 0 ? round(($stdDev / $moyenne) * 100, 2) : 0;
        $cv = $moyenne > 0 ? round($stdDev / $moyenne, 4) : 0;

        $slope = 0;
        if ($count >= 2) {
            $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $count; $i++) {
                $x = $i;
                $y = $totals[$i];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            $denom = ($count * $sumX2) - ($sumX * $sumX);
            if ($denom != 0) {
                $slope = (($count * $sumXY) - ($sumX * $sumY)) / $denom;
            }
        }

        $jours = ['1' => [], '2' => [], '3' => [], '4' => [], '5' => [], '6' => [], '7' => []];
        $semaines = [];
        $weekendRevs = [];
        $weekdayRevs = [];
        foreach ($historique as $h) {
            $dow = (string) date('N', strtotime($h['start_date']));
            $jours[$dow][] = (float) ($h['total_revenus'] ?? 0);
            $yw = date('Y-W', strtotime($h['start_date']));
            if (! isset($semaines[$yw])) {
                $semaines[$yw] = 0;
            }
            $semaines[$yw] += (float) ($h['total_revenus'] ?? 0);
            if (in_array($dow, ['6', '7'])) {
                $weekendRevs[] = (float) ($h['total_revenus'] ?? 0);
            } else {
                $weekdayRevs[] = (float) ($h['total_revenus'] ?? 0);
            }
        }
        $joursMoy = [];
        foreach ($jours as $d => $vals) {
            $joursMoy[$d] = empty($vals) ? 0 : (array_sum($vals) / count($vals));
        }
        arsort($joursMoy);
        reset($joursMoy);
        $meilleurJourSemaine = (int) key($joursMoy);
        end($joursMoy);
        $pireJourSemaine = (int) key($joursMoy);
        arsort($semaines);
        reset($semaines);
        $semainePlusForte = (string) key($semaines);

        $avgWeekend = empty($weekendRevs) ? 0 : (array_sum($weekendRevs) / count($weekendRevs));
        $avgWeekday = empty($weekdayRevs) ? 0 : (array_sum($weekdayRevs) / count($weekdayRevs));
        $weekendRatio = $avgWeekday > 0 ? round((($avgWeekend - $avgWeekday) / $avgWeekday) * 100, 2) : 0;

        $parService = [];
        $serviceData = [];
        foreach ($raw as $row) {
            $kw = $row['keyword'] ?? 'AUTRE';
            if (! isset($serviceData[$kw])) {
                $serviceData[$kw] = ['dates' => [], 'total' => 0, 'count' => 0];
            }
            $serviceData[$kw]['dates'][$row['start_date']] = ($serviceData[$kw]['dates'][$row['start_date']] ?? 0) + (float) ($row['total_revenus'] ?? 0);
            $serviceData[$kw]['total'] += (float) ($row['total_revenus'] ?? 0);
            $serviceData[$kw]['count'] += (int) ($row['nb_cdr'] ?? 0);
        }
        $globalTotal = array_sum(array_column($serviceData, 'total'));
        foreach ($serviceData as $kw => $data) {
            $dates = $data['dates'];
            ksort($dates);
            $vals = array_values($dates);
            $trend = 'stable';
            if (count($vals) >= 2) {
                $midS = (int) floor(count($vals) / 2);
                $f = array_slice($vals, 0, $midS);
                $s = array_slice($vals, $midS);
                $af = $midS > 0 ? (array_sum($f) / $midS) : 0;
                $as = count($s) > 0 ? (array_sum($s) / count($s)) : 0;
                if ($as > $af * 1.02) {
                    $trend = 'hausse';
                } elseif ($as < $af * 0.98) {
                    $trend = 'baisse';
                }
            }
            $parService[] = [
                'keyword' => $kw,
                'total_revenus' => round($data['total'], 3),
                'part_marche' => $globalTotal > 0 ? round(($data['total'] / $globalTotal) * 100, 2) : 0,
                'nb_cdr' => $data['count'],
                'tendance' => $trend,
            ];
        }
        usort($parService, fn ($a, $b) => $b['total_revenus'] <=> $a['total_revenus']);

        $revenusParKeywordTop5 = array_slice(array_map(fn ($s) => [
            'keyword' => $s['keyword'],
            'total' => $s['total_revenus'],
            'part' => $s['part_marche'],
        ], $parService), 0, 5);

        $parType = [];
        $typeData = [];
        foreach ($raw as $row) {
            $st = $row['subscriber_type'] ?? 'INCONNU';
            if (! isset($typeData[$st])) {
                $typeData[$st] = ['dates' => [], 'total' => 0, 'count' => 0, 'abonnes' => 0];
            }
            $typeData[$st]['dates'][$row['start_date']] = ($typeData[$st]['dates'][$row['start_date']] ?? 0) + (float) ($row['total_revenus'] ?? 0);
            $typeData[$st]['total'] += (float) ($row['total_revenus'] ?? 0);
            $typeData[$st]['count'] += (int) ($row['nb_cdr'] ?? 0);
            $typeData[$st]['abonnes'] += (int) ($row['nb_abonnes'] ?? 0);
        }
        $globalTotalType = array_sum(array_column($typeData, 'total'));
        foreach ($typeData as $st => $data) {
            $dates = $data['dates'];
            ksort($dates);
            $vals = array_values($dates);
            $trend = 'stable';
            if (count($vals) >= 2) {
                $midS = (int) floor(count($vals) / 2);
                $f = array_slice($vals, 0, $midS);
                $s = array_slice($vals, $midS);
                $af = $midS > 0 ? (array_sum($f) / $midS) : 0;
                $as = count($s) > 0 ? (array_sum($s) / count($s)) : 0;
                if ($as > $af * 1.02) {
                    $trend = 'hausse';
                } elseif ($as < $af * 0.98) {
                    $trend = 'baisse';
                }
            }
            $parType[] = [
                'subscriber_type' => $st,
                'total_revenus' => round($data['total'], 3),
                'part_marche' => $globalTotalType > 0 ? round(($data['total'] / $globalTotalType) * 100, 2) : 0,
                'revenu_moyen' => $data['abonnes'] > 0 ? round($data['total'] / $data['abonnes'], 3) : 0,
                'nb_cdr' => $data['count'],
                'tendance' => $trend,
            ];
        }

        $servicesCroissance = array_filter($parService, fn ($s) => $s['tendance'] === 'hausse');
        $servicesBaisse = array_filter($parService, fn ($s) => $s['tendance'] === 'baisse');

        return [
            'croissance_journaliere_moyenne' => $croissanceJour,
            'croissance_hebdomadaire' => $croissanceSem,
            'volatilite' => $volatilite,
            'coefficient_variation' => $cv,
            'meilleur_jour_semaine' => $meilleurJourSemaine,
            'pire_jour_semaine' => $pireJourSemaine,
            'semaine_plus_forte' => $semainePlusForte,
            'par_service' => $parService,
            'par_subscriber_type' => $parType,
            'services_croissance' => array_values(array_map(fn ($s) => $s['keyword'], $servicesCroissance)),
            'services_baisse' => array_values(array_map(fn ($s) => $s['keyword'], $servicesBaisse)),
            'moyenne_7j' => $moyenne7j,
            'moyenne_30j' => $moyenne30j,
            'ecart_type' => round($stdDev, 3),
            'tendance_lineaire' => round($slope, 4),
            'variation_weekend_vs_semaine_pct' => $weekendRatio,
            'revenus_par_keyword_top5' => $revenusParKeywordTop5,
        ];
    }

    protected function buildPrompt(array $historique, array $stats, array $metriques, int $horizon): string
    {
        $data = array_map(fn ($h) => [
            'date' => $h['start_date'],
            'revenus_dt' => round((float) ($h['total_revenus'] ?? 0), 3),
            'nb_cdr' => (int) ($h['nb_cdr'] ?? 0),
            'nb_abonnes' => (int) ($h['nb_abonnes'] ?? 0),
            'revenu_moyen' => round((float) ($h['revenu_moyen'] ?? 0), 3),
        ], $historique);

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $joursNoms = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $mj = $metriques['meilleur_jour_semaine'] ?? null;
        $pj = $metriques['pire_jour_semaine'] ?? null;
        $tendanceDir = $metriques['tendance_lineaire'] > 0 ? 'hausse' : 'baisse';
        $tendanceVal = abs($metriques['tendance_lineaire']);

        $prompt = "Tu es un expert en analyse financiere telecom.
Voici les revenus journaliers SMS+ VAS reels des 60 derniers jours en DT :
{$jsonData}

REGLES OBLIGATOIRES :
- Analyse les vraies tendances (hausse/baisse/cycles)
- Respecte la volatilite naturelle des donnees
- Ne genere PAS une progression lineaire parfaite
- Base-toi sur les patterns de la semaine precedente
- Prends en compte les variations weekend vs semaine
- La variation max entre 2 jours consecutifs ne doit pas depasser 25% sauf anomalie justifiee
- Les predictions doivent ressembler aux donnees reelles

Statistiques calculees :
- Moyenne 7j : {$metriques['moyenne_7j']} DT
- Moyenne 30j : {$metriques['moyenne_30j']} DT
- Volatilite (ecart-type) : {$metriques['ecart_type']} DT
- Tendance : {$tendanceDir} de {$tendanceVal} DT/jour
- Meilleur jour semaine : ".($mj ? $joursNoms[$mj] : 'N/A').'
- Revenus weekend vs semaine : '.$metriques['variation_weekend_vs_semaine_pct'].'%

Reponds UNIQUEMENT en JSON valide sans texte avant/apres :
{
  \"predictions_journalieres\": [
    {
      \"date\": \"YYYY-MM-DD\",
      \"jour_semaine\": \"Lundi\",
      \"revenus_predit\": 12.45,
      \"revenus_min\": 10.20,
      \"revenus_max\": 14.80,
      \"confidence\": \"high\",
      \"confidence_pct\": 82,
      \"tendance\": \"stable\",
      \"variation_pct\": -2.3,
      \"facteurs\": [\"Weekend moins actif\", \"Tendance stable semaine\"]
    }
  ],
  \"predictions_par_service\": [
    {
      \"keyword\": \"mb1\",
      \"nom_service\": \"SHOFHA\",
      \"revenus_predit_7j\": 45.50,
      \"tendance\": \"hausse\",
      \"variation_pct\": 5.2
    }
  ],
  \"resume_semaine\": {
    \"total_predit\": 89.30,
    \"meilleur_jour\": {\"date\": \"2026-04-10\", \"montant\": 15.20},
    \"pire_jour\": {\"date\": \"2026-04-13\", \"montant\": 10.80},
    \"comparaison_semaine_precedente_pct\": 3.5
  },
  \"analyse_detaillee\": {
    \"tendance_generale\": \"texte 80 mots base sur vraies donnees\",
    \"facteurs_positifs\": [\"fact1\", \"fact2\", \"fact3\"],
    \"facteurs_risque\": [\"risque1\", \"risque2\"],
    \"opportunites\": [\"opp1\", \"opp2\"],
    \"services_surveiller\": [\"keyword1\", \"keyword2\"]
  },
  \"recommandations\": [
    {
      \"priorite\": \"haute\",
      \"action\": \"texte court\",
      \"impact_estime\": \"texte court\",
      \"delai\": \"immediat\"
    }
  ],
  \"score_fiabilite\": 78,
  \"methodologie\": \"Analyse basee sur tendances 60j + patterns hebdomadaires\"
}

Important : genere exactement '.$horizon.' jours dans predictions_journalieres. Les dates doivent etre consecutives a partir de demain.';

        return $prompt;
    }

    private function parseAiResponse(?string $content): ?array
    {
        if (!$content) return null;

        try {
            $data = json_decode($content, true);

            if (!$data) {
                preg_match('/\{.*\}/s', $content, $matches);
                if ($matches) {
                    $data = json_decode($matches[0], true);
                }
            }

            if (!$data) return null;

            // Assure que score_fiabilite est toujours présent et valide
            if (!isset($data['score_fiabilite']) || $data['score_fiabilite'] === null || $data['score_fiabilite'] === 0) {
                // Score par défaut plus élevé pour les IAs modernes
                $data['score_fiabilite'] = 82;
            }

            $data['score_fiabilite'] = max(1, min(100, (int)$data['score_fiabilite']));

            if (empty($data['predictions_journalieres'])) {
                return null;
            }

            foreach ($data['predictions_journalieres'] as &$pred) {
                $pred['confidence_pct'] = max(1, min(100, (int)($pred['confidence_pct'] ?? 70)));
                $pred['revenus_predit'] = max(0.001, (float)($pred['revenus_predit'] ?? 0));
                $pred['revenus_min'] = max(0.001, (float)($pred['revenus_min'] ?? $pred['revenus_predit'] * 0.8));
                $pred['revenus_max'] = max($pred['revenus_predit'], (float)($pred['revenus_max'] ?? $pred['revenus_predit'] * 1.2));
                $pred['confidence'] = $pred['confidence'] ?? 'medium';
                $pred['tendance'] = $pred['tendance'] ?? 'stable';
                $pred['variation_pct'] = (float)($pred['variation_pct'] ?? 0);
                $pred['facteurs'] = $pred['facteurs'] ?? [];
            }

            return $data;
        } catch (\Exception $e) {
            \Log::warning('[AI] Parse JSON échoué: ' . $e->getMessage() . ' | Content: ' . substr($content, 0, 200));
            return null;
        }
    }

    private function adjustScoreByProvider(array $predictions, string $provider, int $nbJoursHistorique): array
    {
        $baseScore = 40;
        if ($nbJoursHistorique >= 60) $baseScore = 80;
        elseif ($nbJoursHistorique >= 30) $baseScore = 70;
        elseif ($nbJoursHistorique >= 14) $baseScore = 60;
        elseif ($nbJoursHistorique >= 7)  $baseScore = 50;

        $providerBonus = match($provider) {
            'groq'          => 15,  // Bonus augmenté
            'gemini'        => 12,  // Bonus Gemini augmenté significativement
            'php_fallback'  => -5,
            default         => 0,
        };

        $finalScore = max(1, min(100, $baseScore + $providerBonus));

        if (($predictions['score_fiabilite'] ?? 0) > 1) {
            $predictions['score_fiabilite'] = max(1, min(100, $predictions['score_fiabilite'] + $providerBonus));
        } else {
            $predictions['score_fiabilite'] = $finalScore;
        }

        return $predictions;
    }

    public function clearCache(Request $request)
    {
        $horizon = $request->input('horizon', 7);
        $keyword = $request->input('keyword');
        $subscriberType = $request->input('subscriber_type');

        $cacheKey = 'predictions_' . $horizon . '_' . ($keyword ?? 'all') . '_' . ($subscriberType ?? 'all') . '_' . date('Y-m-d-H');

        Cache::forget($cacheKey);
        Cache::forget($cacheKey . '_time');

        return response()->json([
            'message' => 'Cache vidé',
            'key'     => $cacheKey,
        ]);
    }

    protected function parseGroqJson(string $content): array
    {
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        try {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        } catch (\Throwable $e) {
            // ignore, try regex fallback
        }

        if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $content, $matches)) {
            try {
                $decoded = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            try {
                $decoded = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        throw new \RuntimeException('Invalid JSON from Groq: '.json_last_error_msg());
    }

    protected function fallbackPredictions(array $historique, array $stats, array $metriques, int $horizon): array
    {
        $totals = array_map(fn ($h) => (float) ($h['total_revenus'] ?? 0), $historique);
        $count = count($totals);
        $avg = $count > 0 ? (array_sum($totals) / $count) : 0;

        $slope = 0;
        if ($count >= 2) {
            $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $count; $i++) {
                $x = $i;
                $y = $totals[$i];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            $denom = ($count * $sumX2) - ($sumX * $sumX);
            if ($denom != 0) {
                $slope = (($count * $sumXY) - ($sumX * $sumY)) / $denom;
            }
        }

        $predictions = [];
        $totalPredit = 0;
        $bestDay = null;
        $worstDay = null;
        $today = new \DateTime('tomorrow');
        $joursNoms = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        for ($i = 0; $i < $horizon; $i++) {
            $date = (clone $today)->modify("+{$i} days");
            $dateStr = $date->format('Y-m-d');
            $dow = (int) $date->format('N');

            $base = $avg + ($slope * ($count + $i));
            $variation = (rand(-15, 15) / 100);
            $predit = max(0.01, $base * (1 + $variation));

            if (in_array($dow, [6, 7])) {
                $weekendFactor = 1 + ($metriques['variation_weekend_vs_semaine_pct'] / 100);
                $predit = $predit * $weekendFactor;
            }

            $predit = round($predit, 2);
            $min = round($predit * 0.85, 2);
            $max = round($predit * 1.15, 2);
            $totalPredit += $predit;

            if ($bestDay === null || $predit > $bestDay['montant']) {
                $bestDay = ['date' => $dateStr, 'montant' => $predit];
            }
            if ($worstDay === null || $predit < $worstDay['montant']) {
                $worstDay = ['date' => $dateStr, 'montant' => $predit];
            }

            $predictions[] = [
                'date' => $dateStr,
                'jour_semaine' => $joursNoms[$dow],
                'revenus_predit' => $predit,
                'revenus_min' => $min,
                'revenus_max' => $max,
                'confidence' => 'medium',
                'confidence_pct' => 65,
                'tendance' => $slope > 0 ? 'hausse' : ($slope < 0 ? 'baisse' : 'stable'),
                'variation_pct' => round($variation * 100, 1),
                'facteurs' => ['Prediction fallback (Groq unavailable)', 'Based on historical trend'],
            ];
        }

        $servicePreds = [];
        foreach (array_slice($metriques['par_service'] ?? [], 0, 5) as $svc) {
            $svcTotal = ($svc['total_revenus'] ?? 0) / max(1, $count) * $horizon;
            $servicePreds[] = [
                'keyword' => $svc['keyword'],
                'nom_service' => $svc['keyword'],
                'revenus_predit_7j' => round($svcTotal, 2),
                'tendance' => $svc['tendance'] ?? 'stable',
                'variation_pct' => rand(-10, 10),
            ];
        }

        return [
            'predictions_journalieres' => $predictions,
            'predictions_par_service' => $servicePreds,
            'resume_semaine' => [
                'total_predit' => round($totalPredit, 2),
                'meilleur_jour' => $bestDay,
                'pire_jour' => $worstDay,
                'comparaison_semaine_precedente_pct' => round($metriques['croissance_hebdomadaire'] ?? 0, 2),
            ],
            'analyse_detaillee' => [
                'tendance_generale' => 'Fallback prediction based on linear regression and historical volatility. Groq API unavailable.',
                'facteurs_positifs' => ['Sufficient historical data'],
                'facteurs_risque' => ['AI service unavailable'],
                'opportunites' => ['Retry later for enriched AI analysis'],
                'services_surveiller' => array_slice(array_map(fn ($s) => $s['keyword'], $metriques['par_service'] ?? []), 0, 3),
            ],
            'recommandations' => [
                [
                    'priorite' => 'moyenne',
                    'action' => 'Retry AI prediction later',
                    'impact_estime' => 'More accurate analysis with Groq',
                    'delai' => '1 hour',
                ],
            ],
            'score_fiabilite' => 45,
            'methodologie' => 'Fallback PHP: linear regression + random variation ±15%',
            'source' => 'fallback',
        ];
    }
}
