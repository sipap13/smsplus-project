<?php

namespace App\Services;

class StatisticalPredictor
{
    /**
     * Prédit les revenus des N prochains jours avec des méthodes statistiques combinées.
     */
    public function predict(array $historique, int $horizon = 7): array
    {
        if (count($historique) < 3) {
            return $this->fallbackFlat($historique, $horizon);
        }

        $revenus = array_column($historique, 'total_revenus');
        $dates   = array_column($historique, 'start_date');

        $ma7    = $this->movingAverage($revenus, 7);
        $ma14   = $this->movingAverage($revenus, 14);
        $ema    = $this->exponentialMovingAverage($revenus, 0.3);
        $slope  = $this->linearRegressionSlope($revenus);
        $stdDev = $this->standardDeviation($revenus);
        $mean   = array_sum($revenus) / count($revenus);

        $weekdayPattern = $this->analyzeWeekdayPattern($historique);
        $seasonality    = $this->detectSeasonality($revenus);

        $maxDailyChange = $mean * 0.02;
        $slope = max(-$maxDailyChange, min($maxDailyChange, $slope));

        $nbDays     = count($revenus);
        // Ajustement des poids selon la stabilité récente
        $recentVol = $this->standardDeviation(array_slice($revenus, -7));
        $isStable  = ($mean > 0 && $recentVol / $mean < 0.15);

        $weightMa7   = $nbDays >= 7  ? ($isStable ? 0.40 : 0.30) : 0.0;
        $weightMa14  = $nbDays >= 14 ? 0.20 : 0.0;
        $weightEma   = $isStable ? 0.30 : 0.40;
        $weightLR    = 0.10;
        $totalWeight = $weightMa7 + $weightMa14 + $weightEma + $weightLR;
        
        if ($totalWeight > 0) {
            $weightMa7 /= $totalWeight; $weightMa14 /= $totalWeight;
            $weightEma /= $totalWeight; $weightLR   /= $totalWeight;
        }

        $predictions = [];
        $lastDate    = date('Y-m-d'); // On commence à prédire à partir d'aujourd'hui/demain
        $baseValue   = end($revenus);

        for ($i = 1; $i <= $horizon; $i++) {
            $date      = date('Y-m-d', strtotime("tomorrow +".($i-1)." days"));
            $dayOfWeek = (int) date('N', strtotime($date));
            $dayName   = $this->frenchDayName($dayOfWeek);

            // Calcul hybride
            $combined = (($ma7  ?? $mean) * $weightMa7)
                      + (($ma14 ?? $mean) * $weightMa14)
                      + ($ema              * $weightEma)
                      + (($baseValue + ($slope * $i)) * $weightLR);

            // Application des patterns (Saisonnalité hebdo + DOW)
            $weekdayFactor = $weekdayPattern[$dayOfWeek] ?? 1.0;
            $combined *= $weekdayFactor;
            $combined *= ($seasonality[$i % 7] ?? 1.0);
            
            // Lissage pour éviter les sauts brusques
            $combined = max($combined, $mean * 0.4);
            if ($i === 1) {
                $combined = ($combined + $baseValue) / 2; // Transition douce
            }

            $confidence   = max(0, min(100, 100 - ($stdDev / ($mean ?: 1) * 80))); // Plus généreux
            $margin       = $stdDev * (0.8 + ($i - 1) * 0.05); // Intervalle plus serré si stable
            $prevValue    = $i === 1 ? $baseValue : $predictions[$i - 2]['revenus_predit'];
            $variationPct = $prevValue > 0 ? (($combined - $prevValue) / $prevValue) * 100 : 0;
            $tendance     = abs($variationPct) < 1.5 ? 'stable' : ($variationPct > 0 ? 'hausse' : 'baisse');
            $confLevel    = $confidence > 80 ? 'high' : ($confidence > 60 ? 'medium' : 'low');

            $predictions[] = [
                'date'           => $date,
                'jour_semaine'   => $dayName,
                'revenus_predit' => round($combined, 3),
                'revenus_min'    => round(max(0, $combined - $margin), 3),
                'revenus_max'    => round($combined + $margin, 3),
                'confidence'     => $confLevel,
                'confidence_pct' => round($confidence),
                'tendance'       => $tendance,
                'variation_pct'  => round($variationPct, 2),
                'facteurs'       => $this->buildFactors($weekdayFactor, $dayName, $slope, $mean, $i, $stdDev),
            ];
        }

        $totalPredit    = array_sum(array_column($predictions, 'revenus_predit'));
        $meilleur       = collect($predictions)->sortByDesc('revenus_predit')->first();
        $pire           = collect($predictions)->sortBy('revenus_predit')->first();
        $derniers7      = array_slice($revenus, -7);
        $totalPrecedent = array_sum($derniers7);
        $comparaisonPct = $totalPrecedent > 0 ? (($totalPredit - $totalPrecedent) / $totalPrecedent * 100) : 0;

        return [
            'predictions_journalieres' => $predictions,
            'predictions_par_service'  => [],
            'resume_semaine' => [
                'total_predit'  => round($totalPredit, 3),
                'meilleur_jour' => ['date' => $meilleur['date'], 'montant' => $meilleur['revenus_predit']],
                'pire_jour'     => ['date' => $pire['date'],     'montant' => $pire['revenus_predit']],
                'comparaison_semaine_precedente_pct' => round($comparaisonPct, 1),
            ],
            'analyse_detaillee' => [
                'tendance_generale'   => $this->buildAnalysis($slope, $mean, $stdDev, $revenus),
                'facteurs_positifs'   => $this->buildPositives($slope, $weekdayPattern),
                'facteurs_risque'     => $this->buildRisks($stdDev, $mean, $horizon),
                'opportunites'        => [],
                'services_surveiller' => [],
            ],
            'recommandations'  => $this->buildRecommendations($slope, $stdDev, $mean),
            'score_fiabilite'  => $this->computeReliability(count($revenus), $stdDev, $mean),
            'methodologie'     => 'Modèle statistique avancé : Moyenne mobile pondérée, EMA adaptatif, Régression linéaire avec amorti, Correction saisonnière hebdo.',
        ];
    }

    private function movingAverage(array $data, int $window): ?float
    {
        $n = count($data);
        if ($n < $window) return null;
        return array_sum(array_slice($data, -$window)) / $window;
    }

    private function exponentialMovingAverage(array $data, float $alpha): float
    {
        $ema = $data[0];
        foreach (array_slice($data, 1) as $value) {
            $ema = $alpha * $value + (1 - $alpha) * $ema;
        }
        return $ema;
    }

    private function linearRegressionSlope(array $data): float
    {
        $n = count($data);
        if ($n < 2) return 0.0;
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        foreach ($data as $i => $y) {
            $sumX  += $i; $sumY  += $y;
            $sumXY += $i * $y; $sumX2 += $i * $i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        return $denom == 0 ? 0.0 : ($n * $sumXY - $sumX * $sumY) / $denom;
    }

    private function standardDeviation(array $data): float
    {
        $n = count($data);
        if ($n < 2) return 0.0;
        $mean     = array_sum($data) / $n;
        $variance = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $data)) / ($n - 1);
        return sqrt($variance);
    }

    private function analyzeWeekdayPattern(array $historique): array
    {
        $byDay = [];
        foreach ($historique as $row) {
            $dow         = (int) date('N', strtotime($row['start_date']));
            $byDay[$dow][] = (float) $row['total_revenus'];
        }
        $globalMean = array_sum(array_column($historique, 'total_revenus')) / max(1, count($historique));
        $means      = [];
        for ($d = 1; $d <= 7; $d++) {
            if (!empty($byDay[$d])) {
                $dayMean  = array_sum($byDay[$d]) / count($byDay[$d]);
                $means[$d] = max(0.5, min(1.5, $globalMean > 0 ? $dayMean / $globalMean : 1.0));
            } else {
                $means[$d] = 1.0;
            }
        }
        return $means;
    }

    private function detectSeasonality(array $revenus): array
    {
        $n = count($revenus);
        if ($n < 14) return array_fill(0, 7, 1.0);
        $pattern    = [];
        $globalMean = array_sum($revenus) / $n;
        for ($i = 0; $i < 7; $i++) {
            $vals = [];
            for ($j = $i; $j < $n; $j += 7) { $vals[] = $revenus[$j]; }
            $mean       = array_sum($vals) / count($vals);
            $pattern[$i] = max(0.7, min(1.3, $globalMean > 0 ? $mean / $globalMean : 1.0));
        }
        return $pattern;
    }

    private function computeReliability(int $nbDays, float $stdDev, float $mean): int
    {
        $score = 50; // Base plus élevée
        if ($nbDays >= 60)      $score += 20;
        elseif ($nbDays >= 30)  $score += 15;
        elseif ($nbDays >= 14)  $score += 10;
        elseif ($nbDays >= 7)   $score += 5;

        // Pénalité selon la volatilité relative (CV = stdDev / mean)
        if ($mean > 0) {
            $cv = $stdDev / $mean;
            if ($cv < 0.10)      $score += 15; // Très stable
            elseif ($cv < 0.20)  $score += 5;
            elseif ($cv > 0.50)  $score -= 20; // Très instable
            elseif ($cv > 0.35)  $score -= 10;
        }

        $score -= 5; // Pénalité fallback réduite
        return max(0, min(100, $score));
    }

    private function frenchDayName(int $dow): string
    {
        return [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'][$dow] ?? 'Inconnu';
    }

    private function buildFactors(float $wdf, string $dayName, float $slope, float $mean, int $i, float $stdDev): array
    {
        $factors = [];
        if ($wdf > 1.1)           $factors[] = "{$dayName} : jour historiquement fort";
        elseif ($wdf < 0.9)       $factors[] = "{$dayName} : jour historiquement faible";
        if ($slope > 0)           $factors[] = 'Tendance haussière sur 60j';
        elseif ($slope < 0)       $factors[] = 'Tendance baissière sur 60j';
        if ($i > 3)               $factors[] = 'Incertitude croissante à J+' . $i;
        if ($stdDev > $mean * 0.3) $factors[] = 'Volatilité élevée des données';
        return $factors;
    }

    private function buildAnalysis(float $slope, float $mean, float $stdDev, array $revenus): string
    {
        $trend    = $slope > 0.05 ? 'haussière' : ($slope < -0.05 ? 'baissière' : 'stable');
        $vol      = $stdDev / max(1, $mean);
        $volLabel = $vol < 0.15 ? 'faible' : ($vol < 0.30 ? 'modérée' : 'élevée');
        $last7    = array_sum(array_slice($revenus, -7));
        $prev7    = array_sum(array_slice($revenus, -14, 7));
        $evol     = $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100, 1) : 0;
        $sign     = $evol >= 0 ? '+' : '';
        return "La tendance générale est {$trend} avec une volatilité {$volLabel} (CV=" . round($vol * 100) . "%). "
             . "Les 7 derniers jours affichent {$sign}{$evol}% vs la semaine précédente. "
             . "Prédiction basée sur modèle statistique combiné (fallback IA indisponible).";
    }

    private function buildPositives(float $slope, array $weekdayPattern): array
    {
        $items = [];
        if ($slope > 0) $items[] = 'Tendance haussière sur 60 jours';
        $bestDay = array_search(max($weekdayPattern), $weekdayPattern);
        if ($bestDay) $items[] = $this->frenchDayName($bestDay) . ' est historiquement le meilleur jour';
        $items[] = 'Modèle basé sur données réelles PostgreSQL';
        return $items;
    }

    private function buildRisks(float $stdDev, float $mean, int $horizon): array
    {
        $risks = ['Prédiction basée sur calcul statistique (IA indisponible)'];
        if ($stdDev > $mean * 0.3) $risks[] = 'Volatilité élevée réduit la précision';
        if ($horizon > 7)          $risks[] = "Horizon > 7j : incertitude croissante";
        return $risks;
    }

    private function buildRecommendations(float $slope, float $stdDev, float $mean): array
    {
        $recs = [];
        if ($slope < -0.1) {
            $recs[] = ['priorite' => 'haute', 'action' => 'Analyser la baisse des revenus sur les 60 derniers jours', 'impact_estime' => 'Identification cause baisse potentielle', 'delai' => 'immédiat'];
        }
        if ($stdDev > $mean * 0.3) {
            $recs[] = ['priorite' => 'moyenne', 'action' => 'Investiguer la forte volatilité des revenus', 'impact_estime' => 'Meilleure stabilité des prédictions', 'delai' => '1 semaine'];
        }
        $recs[] = ['priorite' => 'basse', 'action' => 'Réactiver les providers IA (Groq / Gemini) pour de meilleures prédictions', 'impact_estime' => 'Score fiabilité +20 points', 'delai' => 'dès que possible'];
        return $recs;
    }

    private function fallbackFlat(array $historique, int $horizon): array
    {
        $mean     = count($historique) > 0 ? array_sum(array_column($historique, 'total_revenus')) / count($historique) : 10.0;
        $lastDate = count($historique) > 0 ? end($historique)['start_date'] : date('Y-m-d');
        $predictions = [];
        for ($i = 1; $i <= $horizon; $i++) {
            $date = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $predictions[] = [
                'date'           => $date,
                'jour_semaine'   => $this->frenchDayName((int) date('N', strtotime($date))),
                'revenus_predit' => round($mean, 3),
                'revenus_min'    => round($mean * 0.8, 3),
                'revenus_max'    => round($mean * 1.2, 3),
                'confidence'     => 'low',
                'confidence_pct' => 30,
                'tendance'       => 'stable',
                'variation_pct'  => 0,
                'facteurs'       => ['Données insuffisantes (< 3 jours)', 'Moyenne simple utilisée'],
            ];
        }
        return [
            'predictions_journalieres' => $predictions,
            'predictions_par_service'  => [],
            'resume_semaine' => [
                'total_predit'  => round($mean * $horizon, 3),
                'meilleur_jour' => ['date' => $predictions[0]['date'], 'montant' => $mean],
                'pire_jour'     => ['date' => end($predictions)['date'], 'montant' => $mean],
                'comparaison_semaine_precedente_pct' => 0,
            ],
            'analyse_detaillee' => [
                'tendance_generale'   => 'Données insuffisantes pour une analyse fiable. Minimum 7 jours recommandé.',
                'facteurs_positifs'   => [],
                'facteurs_risque'     => ['Moins de 3 jours de données disponibles'],
                'opportunites'        => [],
                'services_surveiller' => [],
            ],
            'recommandations' => [['priorite' => 'haute', 'action' => 'Importer plus de données historiques (minimum 7 jours)', 'impact_estime' => 'Prédictions fiables', 'delai' => 'immédiat']],
            'score_fiabilite' => 15,
            'methodologie'    => 'Moyenne simple (données insuffisantes)',
        ];
    }
}
