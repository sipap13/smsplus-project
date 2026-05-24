<?php

namespace Tests\Unit;

use App\Services\EtlMonitorService;
use Tests\TestCase;

class EtlMonitorServiceTest extends TestCase
{
    private EtlMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EtlMonitorService();
    }

    public function testNormalizeRowCounts_total_rows_from_nb_lignes(): void
    {
        $normalized = $this->service->normalizeRowCounts([
            'nb_lignes' => 123,
        ]);

        $this->assertSame(123, $normalized['total_rows']);
    }

    public function testNormalizeRowCounts_processed_rows_from_rows_processed(): void
    {
        $normalized = $this->service->normalizeRowCounts([
            'rows_processed' => 42,
        ]);

        $this->assertSame(42, $normalized['processed_rows']);
    }

    public function testNormalizeRowCounts_processed_rows_from_rows_inserted(): void
    {
        $normalized = $this->service->normalizeRowCounts([
            'rows_inserted' => 77,
        ]);

        $this->assertSame(77, $normalized['processed_rows']);
    }

    public function testNormalizeRowCounts_error_rows_from_rows_skipped(): void
    {
        $normalized = $this->service->normalizeRowCounts([
            'rows_skipped' => 5,
        ]);

        $this->assertSame(5, $normalized['error_rows']);
    }

    public function testNormalizeRowCounts_defaults_processed_rows_to_1_for_system_command_report_when_missing(): void
    {
        $job = new \App\Models\EtlJob();
        $job->category = 'command';

        $normalized = $this->service->normalizeRowCounts([], $job);

        $this->assertSame(1, $normalized['processed_rows']);
    }
}



