<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    protected ChatbotService $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:600',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $user = $request->attributes->get('auth_user');
        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Vous devez être authentifié.',
            ], 401);
        }

        try {
            $result = $this->chatbotService->analyzeQuestion($request->input('question'), $user);

            return response()->json($result);
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => 'Analyse impossible',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
