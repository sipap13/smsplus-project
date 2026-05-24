<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ChatbotService
{
    public function __construct(protected AiProviderService $aiProvider)
    {
    }

    public function analyzeQuestion(string $question, object $user): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new \InvalidArgumentException('La question est vide.');
        }

        $sql = $this->generateSqlQuery($question, $user->role);
        \Illuminate\Support\Facades\Log::info("[Chatbot] SQL generated for '{$question}': {$sql}");
        
        $results = $this->executeQuery($sql);
        $summary = $this->summarizeResults($results);
        $response = $this->generateResponse($question, $summary);

        // Log the job in ra_t_etl_jobs
        $this->logChatbotJob($question, $summary, $user);

        return [
            'question' => $question,
            'response' => $response,
            'data' => $summary,
            'sql_query' => $sql,
        ];
    }

    /**
     * Loggue l'appel au chatbot dans la table ra_t_etl_jobs pour le monitoring.
     */
    protected function logChatbotJob(string $question, array $summary, object $user): void
    {
        try {
            DB::table('ra_t_etl_jobs')->insert([
                'job_name' => 'chatbot_analysis',
                'category' => 'AI_CHAT',
                'source' => 'Groq/Gemini',
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
                'total_rows' => $summary['total_count'] ?? 0,
                'processed_rows' => $summary['total_count'] ?? 0,
                'triggered_by' => $user->id ?? 1,
                'user_email' => $user->email ?? 'unknown',
                'page' => 'Chatbot',
                'metadata' => json_encode([
                    'question' => $question,
                    'is_complete' => $summary['is_complete'] ?? true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[Chatbot] Error logging job: " . $e->getMessage());
        }
    }

    protected function generateSqlQuery(string $question, string $userRole): string
    {
        $prompt = $this->buildSqlPrompt($userRole);

        $aiResponse = $this->aiProvider->complete($prompt, $question, 400, 0);
        $sql = trim($aiResponse['content']);
        $sql = $this->normalizeSql($sql, $userRole);

        return $this->cleanSql($sql);
    }

    /**
     * Corrige et valide les colonnes generees par le LLM.
     *
     * Colonnes OCC seulement : charge_amount, keyword, roaming_type, partner, datasource
     * Colonnes MMG seulement : ne, event_type_orig, event_status, service_type
     * Colonnes communes    : id, a_msisdn, b_msisdn, start_date, start_hour,
     *                         event_type, call_type, subscriber_type, orig_start_time,
     *                         created_at, updated_at
     */
    protected function normalizeSql(string $sql, string $userRole): string
    {
        // ── 1. Corriger les alias de montant OCC (tous roles) ─────────────────
        $sql = str_ireplace(
            ['chrg_amount', 'chg_amount', 'charge_amt'],
            'charge_amount',
            $sql
        );

        // ── 2. Acces OCC uniquement (ANALYSTE_BUSS) ───────────────────────────
        if ($userRole === 'ANALYSTE_BUSS') {
            if (preg_match('/\bra_t_mmg_cdr_det\b/i', $sql)) {
                throw new \RuntimeException(
                    "La requete SQL generee utilise la table MMG alors que le role ANALYSTE_BUSS ne doit acceder qu'a OCC."
                );
            }
            $sql = preg_replace('/\bservice_type\b/i', 'keyword', $sql);
            $sql = preg_replace('/(?<![_a-zA-Z0-9])service(?![_a-zA-Z0-9])/i', 'keyword', $sql);

            return $sql;
        }

        // ── 3. Corrections qualifiees OCC ────────────────────────────────────
        $sql = preg_replace(
            '/\bra_t_occ_cdr_detail\s*\.\s*service_type\b/i',
            'ra_t_occ_cdr_detail.keyword',
            $sql
        );
        $sql = preg_replace(
            '/\bra_t_occ_cdr_detail\s*\.\s*service(?!_type)(?![a-zA-Z0-9_])/i',
            'ra_t_occ_cdr_detail.keyword',
            $sql
        );

        // ── 4. Corrections qualifiees MMG ────────────────────────────────────
        $sql = preg_replace(
            '/\bra_t_mmg_cdr_det\s*\.\s*service(?!_type)(?![a-zA-Z0-9_])/i',
            'ra_t_mmg_cdr_det.service_type',
            $sql
        );

        // ── 5. Detecter un JOIN illegal entre OCC et MMG ──────────────────────
        $hasOcc = (bool) preg_match('/\bra_t_occ_cdr_detail\b/i', $sql);
        $hasMmg = (bool) preg_match('/\bra_t_mmg_cdr_det\b/i', $sql);

        if ($hasOcc && $hasMmg) {
            // Un JOIN direct entre les deux tables est interdit
            if (preg_match('/\bjoin\b/i', $sql)) {
                throw new \RuntimeException(
                    'Les tables OCC et MMG ne doivent pas etre jointes (JOIN). '
                    .'Utilisez deux sous-requetes independantes ou un UNION ALL pour comparer les deux tables.'
                );
            }
        }

        // ── 6. Colonnes OCC-only utilisees sur MMG ────────────────────────────
        $occOnlyColumns = ['charge_amount', 'keyword', 'roaming_type', 'partner', 'datasource'];
        foreach ($occOnlyColumns as $col) {
            if (preg_match('/\bra_t_mmg_cdr_det\s*\.\s*'.$col.'\b/i', $sql)) {
                throw new \RuntimeException(
                    "La colonne '$col' n'existe pas dans la table MMG (ra_t_mmg_cdr_det). "
                    .'Elle appartient uniquement a la table OCC (ra_t_occ_cdr_detail).'
                );
            }
        }

        // ── 7. Colonnes MMG-only utilisees sur OCC ────────────────────────────
        $mmgOnlyColumns = ['ne', 'event_type_orig', 'event_status', 'service_type'];
        foreach ($mmgOnlyColumns as $col) {
            if (preg_match('/\bra_t_occ_cdr_detail\s*\.\s*'.$col.'\b/i', $sql)) {
                throw new \RuntimeException(
                    "La colonne '$col' n'existe pas dans la table OCC (ra_t_occ_cdr_detail). "
                    .'Elle appartient uniquement a la table MMG (ra_t_mmg_cdr_det).'
                );
            }
        }

        // ── 8. References non qualifiees quand seule OCC est presente ─────────
        if ($hasOcc && ! $hasMmg) {
            $sql = preg_replace('/\bservice_type\b/i', 'keyword', $sql);
            $sql = preg_replace('/(?<![_a-zA-Z0-9])service(?![_a-zA-Z0-9])/i', 'keyword', $sql);
        }

        // ── 9. charge_amount non qualifie dans une requete MMG seule ──────────
        if ($hasMmg && ! $hasOcc) {
            if (preg_match('/\bcharge_amount\b/i', $sql)) {
                throw new \RuntimeException(
                    "La colonne charge_amount n'existe pas dans la table MMG (ra_t_mmg_cdr_det). "
                    .'Pour les volumes MMG, utilisez COUNT(*) ou COUNT(b_msisdn).'
                );
            }
        }

        return $sql;
    }

    protected function generateResponse(string $question, array $summary): string
    {
        $prompt = "Tu es un assistant en français qui explique les résultats d'analyse CDR. ";
        $prompt .= 'Ne parle pas de la requête SQL brute. ';
        $prompt .= "CONSIGNES : ";
        $prompt .= "1. Si plusieurs numéros ont la même activité, mentionne-les comme étant ex-æquo. ";
        $prompt .= "2. Si le résultat est tronqué, précise que l'analyse porte sur le total mais que tu n'affiches que le top. ";
        $prompt .= "3. Sois précis sur les chiffres (nombre de transactions, revenus). ";
        $prompt .= "4. Si la question est 'le plus actif' et que tu vois plusieurs résultats avec le même score dans le JSON, cite-les tous. ";
        $prompt .= 'Utilise uniquement le résumé JSON suivant : '.json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'.';

        $aiResponse = $this->aiProvider->complete($prompt, $question, 400, 0.6);
        return trim($aiResponse['content']);
    }

    protected function executeQuery(string $sql): array
    {
        try {
            $results = DB::select($sql);

            return array_map(fn ($row) => (array) $row, $results);
        } catch (Throwable $e) {
            throw new \RuntimeException("Echec de l'execution de la requete SQL: ".$e->getMessage());
        }
    }

    protected function summarizeResults(array $results): array
    {
        $count = count($results);
        return [
            'total_count' => $count,
            'count' => $count, // Compatibilité frontend
            'columns' => $count > 0 ? array_keys($results[0]) : [],
            // Retourne TOUS les résultats si <= 50, sinon tronque pour le contexte de l'IA
            'data' => array_slice($results, 0, 50),
            'sample' => array_slice($results, 0, 50), // Compatibilité
            'is_complete' => $count <= 50,
            'note' => $count > 50 
                ? "Résultats tronqués à 50/{$count}. L'analyse porte sur le total mais l'IA ne voit que le top 50."
                : "Résultats complets ({$count} lignes)."
        ];
    }

    /**
     * Charge les valeurs distinctes de keyword (OCC) et service_type (MMG)
     * depuis la base pour les injecter dans le prompt.
     * On limite a 30 valeurs pour ne pas depasser le contexte du LLM.
     */
    protected function fetchEnumValues(): array
    {
        try {
            $occKeywords = DB::table('ra_t_occ_cdr_detail')
                ->selectRaw('keyword, COUNT(*) as nb')
                ->whereNotNull('keyword')
                ->where('keyword', '!=', '')
                ->groupBy('keyword')
                ->orderByDesc('nb')
                ->limit(30)
                ->pluck('keyword')
                ->toArray();
        } catch (Throwable) {
            $occKeywords = [];
        }

        try {
            $mmgServices = DB::table('ra_t_mmg_cdr_det')
                ->selectRaw('service_type, COUNT(*) as nb')
                ->whereNotNull('service_type')
                ->where('service_type', '!=', '')
                ->groupBy('service_type')
                ->orderByDesc('nb')
                ->limit(30)
                ->pluck('service_type')
                ->toArray();
        } catch (Throwable) {
            $mmgServices = [];
        }

        return [
            'occ_keywords' => $occKeywords,
            'mmg_services' => $mmgServices,
        ];
    }

    /**
     * Recupere la table de correspondance keyword <-> nom_service.
     */
    protected function fetchServiceMapping(): string
    {
        try {
            $mappings = DB::table('ra_t_services')
                ->select('keyword', 'nom_service', 'nom_fournisseur')
                ->where('actif', true)
                ->get();
            
            $text = "TABLE ra_t_services (MAPPING COMMERCIAL) :\n";
            foreach ($mappings as $m) {
                $text .= "- Nom: \"{$m->nom_service}\", Fournisseur: \"{$m->nom_fournisseur}\", Keyword/Technical: \"{$m->keyword}\"\n";
            }
            return $text;
        } catch (Throwable) {
            return "";
        }
    }

    protected function buildSqlPrompt(string $userRole): string
    {
        $enums = $this->fetchEnumValues();
        $mapping = $this->fetchServiceMapping();

        $prompt = 'Tu es un assistant IA qui genere uniquement une requete SQL SELECT valide pour PostgreSQL. ';
        $prompt .= 'Ne fournis aucune explication. Reponds uniquement avec la requete SQL brute, sans bloc markdown. ';
        $prompt .= 'IMPORTANT : La requete DOIT commencer par SELECT. N\'utilise JAMAIS de CTE (WITH ... AS). Utilise toujours des sous-requetes ou des jointures simples. ';
        $prompt .= 'Pour combiner des aggregations entre OCC et MMG, utilise obligatoirement une sous-requete globale (ex: SELECT a_msisdn, SUM(c) FROM (SELECT a_msisdn, COUNT(*) as c FROM occ GROUP BY a_msisdn UNION ALL SELECT a_msisdn, COUNT(*) as c FROM mmg GROUP BY a_msisdn) as combined GROUP BY a_msisdn ORDER BY sum DESC LIMIT 10). N\'utilise jamais de ORDER BY ou LIMIT directement autour du UNION ALL. ';
        $prompt .= "CONTEXTE COMMERCIAL DES SERVICES :\n$mapping\n";
        $prompt .= "Si l'utilisateur demande un service par son nom commercial (ex: Shofha), utilise le Keyword technique correspondant (ex: mb1) dans la clause WHERE sur ra_t_occ_cdr_detail.keyword ou ra_t_mmg_cdr_det.service_type.\n";
        $prompt .= "IMPORTANT : Pour les questions d'analyse (ex: 'le plus actif', 'total des revenus', 'top 5'), effectue TOUJOURS l'agrégation directement en SQL (GROUP BY, COUNT, SUM, ORDER BY, LIMIT). Ne renvoie jamais une liste brute si un calcul est possible. Récupère au moins le TOP 5 des résultats pour avoir du contexte même si l'utilisateur en demande un seul.\n";
        $prompt .= "CONSEIL DE RÉPONSE : Si tes résultats SQL sont limités par un LIMIT ou un TOP, ne dis pas à l'utilisateur que c'est 'le seul' résultat disponible. Dis plutôt que c'est le premier ou le plus important.\n";
        $prompt .= 'REGLES ABSOLUES sur les colonnes — respecte-les strictement ou la requete sera en erreur : ';

        // ── Table OCC (schema exact) ───────────────────────────────────────────
        $prompt .= '[TABLE ra_t_occ_cdr_detail] ';
        $prompt .= 'Colonnes exactes : id, datasource, a_msisdn, b_msisdn, start_date, start_hour, call_type, event_type, subscriber_type, roaming_type, partner, charge_amount, keyword, orig_start_time, created_at, updated_at. ';
        $prompt .= 'charge_amount EXISTE dans OCC et represente le montant facture. ';
        $prompt .= 'La notion de service dans OCC se nomme KEYWORD (jamais service, jamais service_type). ';
        $prompt .= 'INTERDIT pour OCC : colonnes service, service_type, event_status, ne. ';

        if (! empty($enums['occ_keywords'])) {
            $list = implode(', ', array_map(fn ($v) => "'$v'", $enums['occ_keywords']));
            $prompt .= "[POUR RÉFÉRENCE UNIQUEMENT] Valeurs réelles de keyword dans OCC : $list. ";
        }

        // ── Table MMG (schema exact) ───────────────────────────────────────────
        $prompt .= '[TABLE ra_t_mmg_cdr_det] ';
        $prompt .= 'Colonnes exactes : id, ne, a_msisdn, b_msisdn, start_date, start_hour, event_type, event_type_orig, call_type, event_status, subscriber_type, service_type, orig_start_time, created_at, updated_at. ';
        $prompt .= 'ATTENTION CRITIQUE : charge_amount N\'EXISTE PAS dans MMG. N\'utilise JAMAIS charge_amount avec ra_t_mmg_cdr_det. ';
        $prompt .= 'La notion de service dans MMG se nomme SERVICE_TYPE (jamais service seul, jamais keyword). ';
        $prompt .= 'INTERDIT pour MMG : colonnes charge_amount, keyword, roaming_type, partner, datasource. ';

        if (! empty($enums['mmg_services'])) {
            $list = implode(', ', array_map(fn ($v) => "'$v'", $enums['mmg_services']));
            $prompt .= "[POUR RÉFÉRENCE UNIQUEMENT] Valeurs réelles de service_type dans MMG : $list. ";
        }

        // ── Montants ───────────────────────────────────────────────────────────
        $prompt .= 'Pour les revenus/montants : utilise UNIQUEMENT ra_t_occ_cdr_detail.charge_amount (OCC seulement). ';
        $prompt .= 'Pour les volumes MMG : utilise COUNT(*) ou COUNT(b_msisdn), JAMAIS charge_amount. ';
        $prompt .= 'Beaucoup de lignes OCC ont charge_amount NULL ou keyword vide — filtre avec IS NOT NULL si pertinent. ';

        // ── Interdiction JOIN et guide comparaison ─────────────────────────────
        $prompt .= 'INTERDIT ABSOLU : ne fais jamais un JOIN ou INNER JOIN entre ra_t_occ_cdr_detail et ra_t_mmg_cdr_det. ';
        $prompt .= 'Ces deux tables sont independantes et ne partagent pas de cle commune fiable. ';
        $prompt .= 'Pour comparer OCC et MMG, utilise DEUX sous-requetes SELECT independantes dans un UNION ALL ou dans un SELECT englobant. ';
        $prompt .= "Exemple de comparaison correcte : SELECT 'OCC' AS source, COUNT(*) AS nb FROM ra_t_occ_cdr_detail UNION ALL SELECT 'MMG' AS source, COUNT(*) AS nb FROM ra_t_mmg_cdr_det. ";

        // ── Droits par role ───────────────────────────────────────────────────
        if ($userRole === 'ANALYSTE_OP') {
            $prompt .= "L'utilisateur a acces aux deux tables OCC et MMG. ";
        } elseif ($userRole === 'ANALYSTE_BUSS') {
            $prompt .= "L'utilisateur a acces UNIQUEMENT a OCC (ra_t_occ_cdr_detail). N'utilise que ra_t_occ_cdr_detail. ";
        } else {
            $prompt .= "L'utilisateur a acces aux deux tables disponibles. ";
        }

        $prompt .= "\nINSTRUCTIONS FINALES : ";
        $prompt .= "1. Pour les questions d'analyse (ex: 'le plus actif', 'top', 'total'), effectue TOUJOURS l'agrégation en SQL (GROUP BY, COUNT, SUM, ORDER BY). ";
        $prompt .= "2. Inclus TOUJOURS la colonne de calcul (ex: SELECT a_msisdn, COUNT(*) as nb_appels) pour que l'IA puisse voir les chiffres. ";
        $prompt .= "3. Utilise TOUJOURS 'LIMIT 10' (au minimum) pour les classements, et TOUJOURS après le ORDER BY. ";
        $prompt .= "4. Si l'utilisateur demande 'le plus actif', 'le top' ou 'total' sans préciser le type de service : ";
        $prompt .= "   - N'AJOUTE PAS de filtre call_type = 'VAS' automatiquement, sauf si la question mentionne explicitement SMS+. ";
        $prompt .= "   - Affiche le total toutes catégories confondues (VAS, DATA, SMS, VOICE). ";
        $prompt .= "   - Exemple CORRECT : SELECT a_msisdn, COUNT(*) as total FROM ra_t_occ_cdr_detail GROUP BY a_msisdn ORDER BY total DESC LIMIT 10. ";
        $prompt .= "5. NE FILTRE PAS par un service spécifique (keyword = ...) sauf si l'utilisateur le demande explicitement. ";
        $prompt .= "6. Si tes résultats sont limités par un LIMIT, ne dis JAMAIS que c'est le 'seul' résultat de la base. ";
        $prompt .= "7. RÈGLES CRITIQUES : ";
        $prompt .= "   - INCLUS TOUJOURS LA COLONNE DE CALCUL (ex: nb, total, revenus) dans le SELECT. L'IA a besoin de voir ces chiffres pour répondre. ";
        $prompt .= "   - TOUJOURS analyser TOUTES les données : ne JAMAIS ajouter LIMIT avant le ORDER BY final. ";
        $prompt .= "   - Le COUNT(*) doit porter sur toute la table filtrée. ";
        $prompt .= "   - Pour compter les transactions : utilisez COUNT(*) (pas DISTINCT sauf demande explicite). ";
        $prompt .= "   - Pour 'le plus actif', récupère toujours au moins 5 ou 10 lignes pour que l'IA puisse voir s'il y a des égalités.";

        return trim($prompt);
    }

    protected function cleanSql(string $sql): string
    {
        // Retirer les blocs markdown que certains LLM ajoutent (```sql ... ```)
        $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql);
        $sql = preg_replace('/\s*```$/', '', $sql);

        $sql = preg_replace('/[\r\n]+/', ' ', $sql);
        $sql = trim($sql, " \t\n\r\0\x0B;");

        // Accepter SELECT et WITH ... SELECT (CTE) — les deux sont des requêtes en lecture seule
        if (!preg_match('/^\s*(select|with)\b/i', $sql)) {
            \Illuminate\Support\Facades\Log::warning('[Chatbot] SQL rejeté (ne commence pas par SELECT/WITH): ' . substr($sql, 0, 200));
            throw new \RuntimeException('Requete SQL non autorisee.');
        }

        // Mots-clés dangereux (mutation de données)
        if (preg_match('/\b(drop|delete|update|insert|alter|truncate|create|replace|grant|revoke)\b/i', $sql)) {
            throw new \RuntimeException('Requete SQL non autorisee.');
        }

        if (str_contains($sql, ';')) {
            throw new \RuntimeException('Requete SQL non autorisee.');
        }

        return $sql;
    }

    public function getGroqEndpoint(): string
    {
        return config('services.groq.api_url', 'https://api.groq.com/openai/v1/chat/completions');
    }

    public function getGroqModel(): string
    {
        return config('services.groq.model', 'llama3-8b-8192');
    }

    public function getGroqApiKey(): string
    {
        return config('services.groq.api_key') ?? throw new \RuntimeException('La cle GROQ_API_KEY est manquante.');
    }
}
