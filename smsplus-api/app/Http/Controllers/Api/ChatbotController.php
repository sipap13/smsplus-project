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

    public function verifyAnswer(Request $request)
    {
        $question = $request->input('question');
        $answer   = $request->input('answer');

        // Requête de vérification directe sans passer par l'IA
        $verification = [];

        if (preg_match('/plus\s+actif|plus\s+actifs|plus\s+active|plus\s+actives/i', $question) && preg_match('/jour|jours|journ[eé]e|diff[eé]rent/i', $question)) {
            $top = \Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')
                ->where(function ($query) {
                    $query->whereNull('datasource')
                          ->orWhere('datasource', '!=', 'OCC_AGG');
                })
                ->select('a_msisdn',
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT start_date::date) as nb_jours_actifs'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as nb_transactions')
                )
                ->groupBy('a_msisdn')
                ->orderByDesc('nb_jours_actifs')
                ->orderByDesc('nb_transactions')
                ->limit(5)
                ->get();

            $verification = [
                'top_5_msisdns' => $top,
                'total_lignes_analysees' => \Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')
                    ->where(function ($query) {
                        $query->whereNull('datasource')
                              ->orWhere('datasource', '!=', 'OCC_AGG');
                    })
                    ->count(),
                'reponse_chatbot_correcte' => $top->first()?->a_msisdn === $answer,
            ];
        } elseif (str_contains(strtolower($question), 'plus actif')) {
            $top = \Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')
                ->where('call_type', 'VAS')
                ->select('a_msisdn',
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as nb_transactions'),
                    \Illuminate\Support\Facades\DB::raw('SUM(charge_amount) as revenus')
                )
                ->groupBy('a_msisdn')
                ->orderByDesc('nb_transactions')
                ->limit(5)
                ->get();

            $verification = [
                'top_5_msisdns' => $top,
                'total_lignes_analysees' => \Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')
                    ->where('call_type', 'VAS')
                    ->count(),
                'reponse_chatbot_correcte' => $top->first()?->a_msisdn === $answer,
            ];
        }

        return response()->json([
            'question'     => $question,
            'answer'       => $answer,
            'verification' => $verification,
            'sql_utilisee' => 'SELECT a_msisdn, COUNT(*) as nb FROM ra_t_occ_cdr_detail WHERE call_type = \'VAS\' GROUP BY a_msisdn ORDER BY nb DESC LIMIT 5',
        ]);
    }
}
