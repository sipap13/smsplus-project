<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    /**
     * Trigger the background command to import MMG AGG data from the CSV file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importMmgAgg(Request $request): JsonResponse
    {
        try {
            // Queue the import command so the request doesn't block
            Artisan::queue('import:mmg-agg');

            return response()->json([
                'success' => true,
                'message' => 'L\'importation du fichier MMG AGG a été lancée en arrière-plan.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error triggering mmg-agg import: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du lancement de l\'importation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
