<?php

namespace Tests\Unit;

use App\Services\StatisticalPredictor;
use Tests\TestCase;

class StatisticalPredictorTest extends TestCase
{
    private StatisticalPredictor $predictor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predictor = new StatisticalPredictor();
    }

    public function testPredict_with_insufficient_data_falls_back_to_flat(): void
    {
        $historique = [
            ['start_date' => '2026-05-01', 'total_revenus' => 100.0],
            ['start_date' => '2026-05-02', 'total_revenus' => 120.0],
        ];

        $result = $this->predictor->predict($historique, 7);

        $this->assertCount(7, $result['predictions_journalieres']);
        $this->assertSame(110.0, $result['resume_semaine']['total_predit'] / 7); // average of 100 and 120 is 110
        $this->assertSame('Moyenne simple (données insuffisantes)', $result['methodologie']);
    }

    public function testPredict_with_sufficient_data_calculates_advanced_metrics(): void
    {
        $historique = [
            ['start_date' => '2026-05-01', 'total_revenus' => 100.0],
            ['start_date' => '2026-05-02', 'total_revenus' => 105.0],
            ['start_date' => '2026-05-03', 'total_revenus' => 110.0],
            ['start_date' => '2026-05-04', 'total_revenus' => 115.0],
            ['start_date' => '2026-05-05', 'total_revenus' => 120.0],
            ['start_date' => '2026-05-06', 'total_revenus' => 125.0],
            ['start_date' => '2026-05-07', 'total_revenus' => 130.0],
        ];

        $result = $this->predictor->predict($historique, 7);

        $this->assertCount(7, $result['predictions_journalieres']);
        $this->assertGreaterThan(0, $result['score_fiabilite']);
        $this->assertStringContainsString('Modèle statistique avancé', $result['methodologie']);
        $this->assertNotEmpty($result['predictions_journalieres'][0]['facteurs']);
    }
}
