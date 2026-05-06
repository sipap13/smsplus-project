<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ChatbotService
{
    public function analyzeQuestion(string $question, object $user): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new \InvalidArgumentException('La question est vide.');
        }

        $sql = $this->generateSqlQuery($question, $user->role);
        $results = $this->executeQuery($sql);
        $summary = $this->summarizeResults($results);
        $response = $this->generateResponse($question, $summary);

        return [
            'question' => $question,
            'response' => $response,
            'data' => $summary,
            'sql_query' => $sql,
        ];
    }

    protected function generateSqlQuery(string $question, string $userRole): string
    {
        $prompt = $this->buildSqlPrompt($userRole);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->getGroqApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(30)->connectTimeout(30)->retry(2, 500)->post($this->getGroqEndpoint(), [
            'model' => $this->getGroqModel(),
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user',   'content' => $question],
            ],
            'temperature' => 0,
            'max_tokens' => 400,
            'top_p' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Groq SQL generation failed: '.$response->body());
        }

        $sql = trim($response->json('choices.0.message.content', ''));
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
        $prompt = "Tu es un assistant en francais qui explique les resultats d'analyse CDR de maniere claire et concise. ";
        $prompt .= 'Ne parle pas de la requete SQL. ';
        $prompt .= 'Utilise uniquement le resume JSON suivant : '.json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'.';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->getGroqApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(30)->connectTimeout(30)->retry(2, 500)->post($this->getGroqEndpoint(), [
            'model' => $this->getGroqModel(),
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user',   'content' => $question],
            ],
            'temperature' => 0.6,
            'max_tokens' => 400,
            'top_p' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Groq response generation failed: '.$response->body());
        }

        return trim($response->json('choices.0.message.content', ''));
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
        return [
            'count' => count($results),
            'columns' => count($results) > 0 ? array_keys($results[0]) : [],
            'sample' => array_slice($results, 0, 10),
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
        $prompt .= "CONTEXTE COMMERCIAL DES SERVICES :\n$mapping\n";
        $prompt .= "Si l'utilisateur demande un service par son nom commercial (ex: Shofha), utilise le Keyword technique correspondant (ex: mb1) dans la clause WHERE sur ra_t_occ_cdr_detail.keyword ou ra_t_mmg_cdr_det.service_type.\n";
        $prompt .= 'REGLES ABSOLUES sur les colonnes — respecte-les strictement ou la requete sera en erreur : ';

        // ── Table OCC (schema exact) ───────────────────────────────────────────
        $prompt .= '[TABLE ra_t_occ_cdr_detail] ';
        $prompt .= 'Colonnes exactes : id, datasource, a_msisdn, b_msisdn, start_date, start_hour, call_type, event_type, subscriber_type, roaming_type, partner, charge_amount, keyword, orig_start_time, created_at, updated_at. ';
        $prompt .= 'charge_amount EXISTE dans OCC et represente le montant facture. ';
        $prompt .= 'La notion de service dans OCC se nomme KEYWORD (jamais service, jamais service_type). ';
        $prompt .= 'INTERDIT pour OCC : colonnes service, service_type, event_status, ne. ';

        if (! empty($enums['occ_keywords'])) {
            $list = implode(', ', array_map(fn ($v) => "'$v'", $enums['occ_keywords']));
            $first = $enums['occ_keywords'][0];
            $prompt .= "Valeurs reelles de keyword dans OCC : $list. ";
            $prompt .= "Exemple correct OCC : WHERE ra_t_occ_cdr_detail.keyword = '$first'. ";
        }

        // ── Table MMG (schema exact) ───────────────────────────────────────────
        $prompt .= '[TABLE ra_t_mmg_cdr_det] ';
        $prompt .= 'Colonnes exactes : id, ne, a_msisdn, b_msisdn, start_date, start_hour, event_type, event_type_orig, call_type, event_status, subscriber_type, service_type, orig_start_time, created_at, updated_at. ';
        $prompt .= 'ATTENTION CRITIQUE : charge_amount N\'EXISTE PAS dans MMG. N\'utilise JAMAIS charge_amount avec ra_t_mmg_cdr_det. ';
        $prompt .= 'La notion de service dans MMG se nomme SERVICE_TYPE (jamais service seul, jamais keyword). ';
        $prompt .= 'INTERDIT pour MMG : colonnes charge_amount, keyword, roaming_type, partner, datasource. ';

        if (! empty($enums['mmg_services'])) {
            $list = implode(', ', array_map(fn ($v) => "'$v'", $enums['mmg_services']));
            $first = $enums['mmg_services'][0];
            $prompt .= "Valeurs reelles de service_type dans MMG : $list. ";
            $prompt .= "Exemple correct MMG : WHERE ra_t_mmg_cdr_det.service_type = '$first'. ";
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
            $prompt .= "L'utilisateur a acces aux deux tables OCC et MMG.";
        } elseif ($userRole === 'ANALYSTE_BUSS') {
            $prompt .= "L'utilisateur a acces UNIQUEMENT a OCC (ra_t_occ_cdr_detail). N'utilise que ra_t_occ_cdr_detail.";
        } else {
            $prompt .= "L'utilisateur a acces aux deux tables disponibles.";
        }

        return trim($prompt);
    }

    protected function cleanSql(string $sql): string
    {
        // Retirer les blocs markdown que certains LLM ajoutent (```sql ... ```)
        $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql);
        $sql = preg_replace('/\s*```$/', '', $sql);

        $sql = preg_replace('/[\r\n]+/', ' ', $sql);
        $sql = trim($sql, " \t\n\r\0\x0B;");

        if (! preg_match('/^\s*select\b/i', $sql)) {
            throw new \RuntimeException('Requete SQL non autorisee.');
        }

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
