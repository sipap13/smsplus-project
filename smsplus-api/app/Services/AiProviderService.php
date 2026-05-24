<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiProviderService
{
    private string $lastProvider = 'none';
    private array $providerStats = [];

    public function getLastProvider(): string { return $this->lastProvider; }
    public function getProviderStats(): array { return $this->providerStats; }

    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 1000, float $temperature = 0.3, ?array $data = null, ?string $customModel = null): array
    {
        // Truncate user message if too large (Groq limit is ~6000 tokens)
        // 1 token approx 3 chars for JSON/dense text.
        $charLimit = 4000 * 3; 
        if (strlen($userMessage) > $charLimit) {
            Log::warning('[AI] User message too large (' . strlen($userMessage) . ' chars), truncating.');
            $userMessage = substr($userMessage, 0, $charLimit) . "... [TRUNCATED DUE TO SIZE]";
        }

        // Déterminer le type de requête UNE SEULE FOIS et le réutiliser partout
        // prediction → JSON obligatoire | chatbot SQL/texte → texte libre accepté
        $isPrediction = stripos($systemPrompt, 'JSON') !== false
            && stripos($systemPrompt, 'sql') === false;

        // 1. Groq
        try {
            $result = $this->callGroq($systemPrompt, $userMessage, $maxTokens, $temperature, $customModel);
            $result = $this->cleanAiResponse($result);

            // Pour chatbot SQL : extraire le SELECT si Groq ajoute du texte avant
            if (!$isPrediction) {
                $result = $this->extractSqlOrText($result);
            }
            
            if ($isPrediction && !$this->isValidAiResponse($result)) {
                throw new \Exception('Groq a retourné un JSON invalide ou tronqué (trop de tokens).');
            }
            if (empty(trim($result))) {
                throw new \Exception('Groq a retourné une réponse vide.');
            }

            $this->lastProvider = 'groq';
            $this->logProviderCall('groq', true);
            return ['content' => $result, 'provider' => 'groq', 'fallback' => false, 'model' => $customModel ?: config('services.groq.model')];
        } catch (\Exception $e) {
            Log::error('[AI] Groq error: ' . $e->getMessage());
            $this->logProviderCall('groq', false);
            $this->logFallbackEvent('groq', $e->getMessage());
        }

        // 2. Mistral AI (Fallback)
        if (config('services.mistral.enabled')) {
            try {
                // Mistral supporte des fenêtres de contexte larges
                $mistralMaxTokens = max($maxTokens, 4000); 
                $mistralResult = $this->callMistral($systemPrompt, $userMessage, $mistralMaxTokens, $temperature);
                $mistralContent = $this->cleanAiResponse($mistralResult['content']);

                // Pour chatbot SQL : extraire le SELECT si Mistral ajoute du texte avant
                if (!$isPrediction) {
                    $mistralContent = $this->extractSqlOrText($mistralContent);
                }

                if ($isPrediction && !$this->isValidAiResponse($mistralContent)) {
                    Log::warning('[AI] Mistral raw response (prediction): ' . substr($mistralContent, 0, 400));
                    throw new \Exception('Mistral a retourné un JSON invalide ou tronqué.');
                }

                if (empty(trim($mistralContent))) {
                    throw new \Exception('Mistral a retourné une réponse vide.');
                }

                $this->lastProvider = 'mistral';
                $this->logProviderCall('mistral', true);
                return [
                    'content' => $mistralContent, 
                    'provider' => 'mistral', 
                    'fallback' => true,
                    'model' => $mistralResult['model']
                ];
            } catch (\Exception $e) {
                Log::error('[AI] Mistral error: ' . $e->getMessage());
                $this->logProviderCall('mistral', false);
                $this->logFallbackEvent('mistral', $e->getMessage());
            }
        }

        // 3. PHP fallback
        $this->lastProvider = 'php_fallback';
        $this->logProviderCall('php_fallback', true);
        
        $fallbackContent = $this->generateStatisticalFallback($systemPrompt, $userMessage, $data);
        return ['content' => $fallbackContent, 'provider' => 'php_fallback', 'fallback' => true, 'model' => 'statistical_model_v2', 'data' => $data];
    }

    /**
     * Strip markdown code fences and return clean JSON string.
     * Gemini often wraps responses in ```json ... ```
     */
    private function cleanAiResponse(string $content): string
    {
        // Remove markdown code fences: ```json ... ``` or ``` ... ```
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        
        // If still not valid, try to extract the first {...} block
        if (!json_decode(trim($cleaned), true)) {
            preg_match('/\{.*\}/s', $content, $matches);
            if ($matches) {
                return $matches[0];
            }
        }
        
        return trim($cleaned);
    }

    private function isValidAiResponse(string $content): bool
    {
        if (!$content) return false;
        
        $cleaned = $this->cleanAiResponse($content);
        return json_decode($cleaned, true) !== null;
    }

    private function callGroq(string $system, string $user, int $maxTokens, float $temperature, ?string $customModel = null): string
    {
        // On augmente le délai de retry pour Groq en cas de 429
        $apiKey = config('services.groq.api_key');
        $apiUrl = config('services.groq.url');
        $model = $customModel ?: config('services.groq.model');
        $timeout = 45;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout($timeout)
        ->retry(3, 2000, function ($exception, $request) {
            return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                   ($exception->getCode() === 429) || 
                   ($exception->getCode() >= 500);
        })
        ->post($apiUrl, [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ]);

        if ($response->failed()) {
            throw new \Exception("Groq HTTP {$response->status()}: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    protected function callMistral(string $systemPrompt, string $userMessage, int $maxTokens, float $temperature): array
    {
        $apiKey = config('services.mistral.api_key');
        if (!$apiKey) {
            throw new \Exception('La clé MISTRAL_API_KEY est manquante.');
        }

        $model = config('services.mistral.model', 'mistral-small-latest');
        $url   = config('services.mistral.url', 'https://api.mistral.ai/v1/chat/completions');

        // Pour les prédictions financières, on force une temperature basse (plus précis)
        $effectiveTemperature = min($temperature, 0.2);

        // response_format json_object uniquement pour les requêtes de prédiction (JSON explicitement demandé)
        // Pour le chatbot SQL, on laisse Mistral répondre en texte libre (SQL pur)
        $isPredictionRequest = stripos($systemPrompt, 'JSON') !== false 
            && stripos($systemPrompt, 'sql') === false;

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'max_tokens'  => $maxTokens,
            'temperature' => $isPredictionRequest ? $effectiveTemperature : $temperature,
        ];

        // Activer le mode JSON natif seulement pour les prédictions
        if ($isPredictionRequest) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(60)
        ->retry(2, 1500, function ($exception) {
            return $exception instanceof \Illuminate\Http\Client\ConnectionException;
        })
        ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception("Mistral HTTP {$response->status()}: " . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (!$content) {
            throw new \Exception('Mistral a retourné une réponse vide.');
        }

        Log::info('[AI] Mistral success with model: ' . $model . ' | mode: ' . ($isPredictionRequest ? 'json' : 'text'));
        return ['content' => $content, 'model' => $model];
    }


    private function generateStatisticalFallback(string $systemPrompt, string $userMessage, ?array $data): string
    {
        $q = strtolower($userMessage);

        // 1. Requête SQL de secours (chatbot) — context-aware
        if (str_contains(strtolower($systemPrompt), 'sql') || str_contains(strtolower($systemPrompt), 'select')) {

            // Comparaison OCC vs MMG
            if ((str_contains($q, 'compar') || str_contains($q, 'vs') || str_contains($q, 'versus'))
                && (str_contains($q, 'occ') || str_contains($q, 'mmg'))) {
                return "SELECT 'OCC' as source, COUNT(*) as nb_transactions, COUNT(DISTINCT a_msisdn) as nb_abonnes FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS'"
                    . " UNION ALL "
                    . "SELECT 'MMG' as source, COUNT(*) as nb_transactions, COUNT(DISTINCT a_msisdn) as nb_abonnes FROM ra_t_mmg_cdr_det WHERE start_date >= CURRENT_DATE - INTERVAL '7 days'";
            }

            // Numéro le plus actif / top MSISDN
            if (str_contains($q, 'plus actif') || str_contains($q, 'numéro actif') || str_contains($q, 'numero actif')
                || (str_contains($q, 'actif') && (str_contains($q, 'numéro') || str_contains($q, 'numero') || str_contains($q, 'msisdn')))) {
                return "SELECT a_msisdn, COUNT(*) as nb_transactions, SUM(charge_amount) as total_revenus FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS' GROUP BY a_msisdn ORDER BY nb_transactions DESC LIMIT 10";
            }

            // Nombre d'abonnés actifs (total)
            if (str_contains($q, 'combien') && (str_contains($q, 'abonné') || str_contains($q, 'actif'))) {
                return "SELECT COUNT(DISTINCT a_msisdn) as nb_abonnes_actifs, COUNT(*) as nb_transactions, SUM(charge_amount) as total_revenus FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS'";
            }

            // Abonnés actifs générique
            if (str_contains($q, 'abonné') || str_contains($q, 'actif') || str_contains($q, 'msisdn')) {
                return "SELECT a_msisdn, COUNT(*) as nb_transactions, SUM(charge_amount) as total_revenus FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS' GROUP BY a_msisdn ORDER BY nb_transactions DESC LIMIT 10";
            }

            // Revenus / chiffre d'affaires
            if (str_contains($q, 'revenu') || str_contains($q, 'montant') || str_contains($q, 'chiffre')) {
                return "SELECT start_date::date as jour, SUM(charge_amount) as total_revenus, COUNT(*) as nb_transactions FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS' GROUP BY start_date::date ORDER BY jour DESC";
            }

            // MMG only
            if (str_contains($q, 'mmg')) {
                return "SELECT start_date::date as jour, COUNT(*) as nb_appels, COUNT(DISTINCT a_msisdn) as nb_abonnes FROM ra_t_mmg_cdr_det WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' GROUP BY start_date::date ORDER BY jour DESC";
            }

            // OCC par défaut
            return "SELECT start_date::date as jour, COUNT(*) as nb_transactions, SUM(charge_amount) as total_revenus, COUNT(DISTINCT a_msisdn) as nb_abonnes FROM ra_t_occ_cdr_detail WHERE start_date >= CURRENT_DATE - INTERVAL '7 days' AND call_type = 'VAS' GROUP BY start_date::date ORDER BY jour DESC";
        }

        // 2. If it's a prediction request with data
        if (strpos($userMessage, 'prédictions') !== false && $data && isset($data['historique'])) {
            $historique = $data['historique'];
            if (count($historique) > 0) {
                $lastWeekRevenue = array_sum(array_column(array_slice($historique, -7), 'total_revenus'));
                $avgDailyRevenue = $lastWeekRevenue / 7;
                
                $predictions = [];
                for ($i = 1; $i <= 7; $i++) {
                    $date = date('Y-m-d', strtotime("+$i days"));
                    $variation = (rand(80, 120) / 100);
                    $predictedRevenue = $avgDailyRevenue * $variation;
                    
                    $predictions[] = [
                        'date' => $date,
                        'revenus_predit' => round($predictedRevenue, 2),
                        'revenus_min' => round($predictedRevenue * 0.8, 2),
                        'revenus_max' => round($predictedRevenue * 1.2, 2),
                        'tendance' => $variation > 1 ? 'hausse' : ($variation < 1 ? 'baisse' : 'stable'),
                        'variation_pct' => round(($variation - 1) * 100, 1),
                        'confidence_pct' => 65,
                        'facteurs' => ['Analyse statistique', 'Tendance historique']
                    ];
                }
                
                return json_encode([
                    'predictions_journalieres' => $predictions,
                    'score_fiabilite' => 65,
                    'methodologie' => 'Analyse statistique basée sur les tendances historiques (mode fallback)',
                    'ai_provider' => 'php_fallback',
                    'ai_model' => 'statistical_model_v2',
                    'ai_fallback' => true
                ]);
            }
        }

        // 3. If it's a response generation request (summarizing results)
        if (str_contains(strtolower($systemPrompt), 'assistant') || str_contains(strtolower($systemPrompt), 'explique')) {
            return "Désolé, les services d'intelligence artificielle sont actuellement surchargés. Basé sur les données brutes, voici ce que j'ai trouvé. (Note: Cette réponse est générée en mode secours).";
        }
        
        // Default fallback response
        return json_encode([
            'predictions_journalieres' => [],
            'score_fiabilite' => 50,
            'methodologie' => 'Mode fallback - données insuffisantes pour analyse IA',
            'ai_provider' => 'php_fallback',
            'ai_model' => 'statistical_model_v2',
            'ai_fallback' => true,
            'message' => 'Prédictions statistiques générées en mode fallback'
        ]);
    }

    public function healthCheck(): array
    {
        $results = [];
        $start = microtime(true);
        try {
            $this->callGroq('Réponds uniquement OK.', 'Test', 10, 0.0);
            $results['groq'] = ['available' => true, 'ms' => (int)((microtime(true) - $start) * 1000)];
        } catch (\Exception $e) {
            $results['groq'] = ['available' => false, 'ms' => (int)((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }

        if (config('services.gemini.enabled')) {
            $start = microtime(true);
            try {
                $this->callGemini('Réponds uniquement OK.', 'Test', 50);
                $results['gemini'] = ['available' => true, 'ms' => (int)((microtime(true) - $start) * 1000)];
            } catch (\Exception $e) {
                $results['gemini'] = ['available' => false, 'ms' => (int)((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
            }
        } else {
            $results['gemini'] = ['available' => false, 'ms' => 0, 'reason' => 'disabled'];
        }

        $active = 'php_fallback';
        if ($results['groq']['available'])   $active = 'groq';
        elseif ($results['gemini']['available']) $active = 'gemini';

        $results['active']         = $active;
        $results['fallback_ready'] = true;
        return $results;
    }

    private function logProviderCall(string $provider, bool $success): void
    {
        $this->providerStats[$provider] = ['success' => $success, 'time' => now()->toISOString()];
    }

    private function logFallbackEvent(string $provider, string $reason): void
    {
        try {
            DB::table('ra_t_etl_jobs')->insert([
                'job_name'   => 'ai_fallback_' . $provider,
                'category'   => 'AI',
                'status'     => 'fallback',
                'error_message' => substr($reason, 0, 500),
                'started_at'    => now(),
                'finished_at'   => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('[AI] Impossible de logger dans ra_t_etl_jobs : ' . $e->getMessage());
        }
    }

    /**
     * Extrait le SQL pur si l'IA a ajouté du texte introductif avant SELECT/WITH.
     * Si le contenu ne ressemble pas à du SQL, retourne le texte tel quel (pour les réponses chatbot).
     */
    private function extractSqlOrText(string $content): string
    {
        $content = trim($content);

        // Si ça commence déjà par SELECT ou WITH → parfait, rien à faire
        if (preg_match('/^\s*(select|with)\b/i', $content)) {
            return $content;
        }

        // Chercher la première occurrence de SELECT ou WITH en début de ligne
        if (preg_match('/^(SELECT|WITH)\b.*/im', $content, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1];
            $extracted = trim(substr($content, $offset));
            Log::info('[AI] extractSqlOrText: stripped preamble (' . $offset . ' chars)');
            return $extracted;
        }

        // Pas de SQL trouvé → c'est une réponse texte (generateResponse), on la retourne entière
        return $content;
    }
}
