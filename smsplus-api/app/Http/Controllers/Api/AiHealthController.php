<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiProviderService;
use Illuminate\Http\Request;

class AiHealthController extends Controller
{
    public function __construct(
        private AiProviderService $aiProvider
    ) {}

    /**
     * GET /api/ai/health
     * Teste Groq + Gemini et retourne leur statut.
     */
    public function health(): \Illuminate\Http\JsonResponse
    {
        $result = $this->aiProvider->healthCheck();
        return response()->json($result);
    }
}
