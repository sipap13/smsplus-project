<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        // Le middleware auth.api stocke l'utilisateur dans les attributs de la requête,
        // pas dans le garde Laravel standard. On utilise donc attributes->get('auth_user').
        $user = $request->attributes->get('auth_user');

        if (! $user) {
            return response()->json([
                'response' => 'Non autorisé.',
                'error' => 'Utilisateur non authentifié.',
            ], 401);
        }

        try {
            $result = $this->chatbot->analyzeQuestion(
                $request->input('message'),
                $user
            );

            return response()->json([
                'response' => $result['response'],
                'data' => $result['data'],
            ]);
        } catch (\Exception $e) {
            $isTimeout = str_contains($e->getMessage(), 'cURL error 28')
                || str_contains($e->getMessage(), 'timed out')
                || str_contains($e->getMessage(), 'Connection timed out');
            return response()->json([
                'response' => $isTimeout
                    ? 'Le service IA est temporairement inaccessible. Veuillez réessayer.'
                    : 'Désolé, je ne peux pas répondre pour le moment.',
                'error' => $e->getMessage(),
            ], $isTimeout ? 503 : 500);
        }
    }
}
