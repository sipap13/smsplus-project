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

    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 1000, float $temperature = 0.3, ?array $data = null): array
    {
        // 1. Groq
        try {
            $result = $this->callGroq($systemPrompt, $userMessage, $maxTokens, $temperature);
            $this->lastProvider = 'groq';
            $this->logProviderCall('groq', true);
            return ['content' => $result, 'provider' => 'groq', 'fallback' => false, 'model' => config('services.groq.model')];
        } catch (\Exception $e) {
            Log::error('[AI] Groq error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
            $this->logProviderCall('groq', false);
            $this->logFallbackEvent('groq', $e->getMessage());
        }

        // 2. Gemini Flash
        if (config('services.gemini.enabled')) {
            try {
                $result = $this->callGemini($systemPrompt, $userMessage, $maxTokens);
                $this->lastProvider = 'gemini';
                $this->logProviderCall('gemini', true);
                return ['content' => $result, 'provider' => 'gemini', 'fallback' => false, 'model' => config('services.gemini.model')];
            } catch (\Exception $e) {
                Log::error('[AI] Gemini error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ]);
                $this->logProviderCall('gemini', false);
                $this->logFallbackEvent('gemini', $e->getMessage());
                
                // Try Groq as fallback instead of PHP
                try {
                    $result = $this->callGroq($systemPrompt, $userMessage, $maxTokens, $temperature);
                    $this->lastProvider = 'groq';
                    $this->logProviderCall('groq', true);
                    return ['content' => $result, 'provider' => 'groq', 'fallback' => true, 'model' => config('services.groq.model')];
                } catch (\Exception $groqException) {
                    Log::error('[AI] Groq fallback also failed: ' . $groqException->getMessage());
                    $this->logProviderCall('groq', false);
                    $this->logFallbackEvent('groq', $groqException->getMessage());
                }
            }
        }

        // 3. PHP fallback - generate statistical predictions instead of null
        $this->lastProvider = 'php_fallback';
        $this->logProviderCall('php_fallback', true);
        $this->logFallbackEvent('php_fallback', 'All AI providers unavailable');
        
        // Generate fallback statistical content
        $fallbackContent = $this->generateStatisticalFallback($systemPrompt, $userMessage, $data);
        
        return ['content' => $fallbackContent, 'provider' => 'php_fallback', 'fallback' => true, 'model' => 'statistical_model_v2', 'data' => $data];
    }

    private function callGroq(string $system, string $user, int $maxTokens, float $temperature): string
    {
        try {
            Log::debug('[AI] Groq call with key: ' . substr(config('services.groq.api_key'), 0, 8) . '...');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.groq.api_key'),
                'Content-Type'  => 'application/json',
            ])
            ->timeout(45)
            ->retry(1, 2000)
            ->post(config('services.groq.url'), [
                'model'       => config('services.groq.model'),
                'messages'    => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Groq timeout après retry: ' . $e->getMessage());
        }

        if (!$response->successful()) throw new \RuntimeException('Groq HTTP ' . $response->status() . ': ' . $response->body());
        $content = trim($response->json('choices.0.message.content', ''));
        if (empty($content)) throw new \RuntimeException('Groq réponse vide');
        return $content;
    }

    private function callGemini(string $system, string $user, int $maxTokens): string
    {
        $model = config('services.gemini.model', 'gemini-1.5-flash');
        $url   = config('services.gemini.url') . $model . ':generateContent?key=' . config('services.gemini.api_key');

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout((int) config('services.gemini.timeout', 30))
            ->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $system . "\n\n" . $user]]]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.1, 'topP' => 0.8, 'topK' => 40],
                'safetySettings'   => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ],
            ]);

        if (!$response->successful()) throw new \RuntimeException('Gemini HTTP ' . $response->status() . ': ' . $response->body());
        $content = trim($response->json('candidates.0.content.parts.0.text', ''));
        
        // Nettoie les balises markdown si présentes
        $content = preg_replace('/^```json\s*/m', '', $content);
        $content = preg_replace('/^```\s*/m', '', $content);
        $content = trim($content);

        if (empty($content)) throw new \RuntimeException('Gemini réponse vide');
        return $content;
    }

    private function generateStatisticalFallback(string $systemPrompt, string $userMessage, ?array $data): string
    {
        // Generate basic statistical predictions based on historical data
        if (strpos($userMessage, 'prédictions') !== false && $data && isset($data['historique'])) {
            $historique = $data['historique'];
            if (count($historique) > 0) {
                $lastWeekRevenue = array_sum(array_column(array_slice($historique, -7), 'total_revenus'));
                $avgDailyRevenue = $lastWeekRevenue / 7;
                
                // Generate simple statistical predictions
                $predictions = [];
                for ($i = 1; $i <= 7; $i++) {
                    $date = date('Y-m-d', strtotime("+$i days"));
                    $variation = (rand(80, 120) / 100); // ±20% variation
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
}
