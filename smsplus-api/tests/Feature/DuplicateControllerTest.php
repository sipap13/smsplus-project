<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicateControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken = 'admin-token-xyz';
    private string $opToken = 'op-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create ADMIN user
        $adminId = DB::table('ra_t_users')->insertGetId([
            'email' => 'admin@smsplus.tn',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create ADMIN token
        DB::table('ra_t_api_tokens')->insert([
            'user_id' => $adminId,
            'token_hash' => hash('sha256', $this->adminToken),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create ANALYSTE_OP user
        $opId = DB::table('ra_t_users')->insertGetId([
            'email' => 'op@smsplus.tn',
            'password' => bcrypt('password'),
            'role' => 'ANALYSTE_OP',
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create ANALYSTE_OP token
        DB::table('ra_t_api_tokens')->insert([
            'user_id' => $opId,
            'token_hash' => hash('sha256', $this->opToken),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test duplicate detection for OCC CDRs.
     */
    public function test_occ_duplicate_detection(): void
    {
        $date = now()->format('Y-m-d');

        // Insert unique OCC record
        DB::table('ra_t_occ_cdr_detail')->insert([
            'a_msisdn' => '21699000111',
            'b_msisdn' => '21699999999',
            'start_date' => $date,
            'start_hour' => 10,
            'charge_amount' => 0.500,
            'keyword' => 'SMS1',
            'call_type' => 'VAS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert 3 identical OCC records (duplicates)
        $dupIds = [];
        for ($i = 0; $i < 3; $i++) {
            $dupIds[] = DB::table('ra_t_occ_cdr_detail')->insertGetId([
                'a_msisdn' => '21699222333',
                'b_msisdn' => '21688888888',
                'start_date' => $date,
                'start_hour' => 14,
                'charge_amount' => 1.200,
                'keyword' => 'SMS2',
                'call_type' => 'VAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Call endpoint with OP token
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->opToken)
            ->getJson("/api/duplicates/occ?date_debut={$date}");

        $response->assertStatus(200);

        $data = $response->json();
        // Should only return the duplicate group, i.e., 1 entry with occurrences = 3
        $this->assertCount(1, $data);
        $this->assertEquals('21699222333', $data[0]['a_msisdn']);
        $this->assertEquals(3, $data[0]['occurrences']);
        $this->assertEqualsWithDelta(3.6, $data[0]['revenu_duplique'], 0.001);
        $this->assertEqualsWithDelta(2.4, $data[0]['revenu_a_corriger'], 0.001);

        // Check array IDs
        $responseIds = $data[0]['ids'];
        sort($responseIds);
        sort($dupIds);
        $this->assertEquals($dupIds, $responseIds);
    }

    /**
     * Test duplicate detection for MMG CDRs.
     */
    public function test_mmg_duplicate_detection(): void
    {
        $date = now()->format('Y-m-d');

        // Insert unique MMG record
        DB::table('ra_t_mmg_cdr_det')->insert([
            'a_msisdn' => '21699000111',
            'b_msisdn' => '21699999999',
            'start_date' => $date,
            'start_hour' => 10,
            'event_type' => 'MT',
            'service_type' => 'SMS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert 2 identical MMG records (duplicates)
        $dupIds = [];
        for ($i = 0; $i < 2; $i++) {
            $dupIds[] = DB::table('ra_t_mmg_cdr_det')->insertGetId([
                'a_msisdn' => '21699333444',
                'b_msisdn' => '21677777777',
                'start_date' => $date,
                'start_hour' => 11,
                'event_type' => 'MO',
                'service_type' => 'SMS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Call endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->opToken)
            ->getJson("/api/duplicates/mmg?date_debut={$date}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('21699333444', $data[0]['a_msisdn']);
        $this->assertEquals(2, $data[0]['occurrences']);

        // Check IDs
        $responseIds = $data[0]['ids'];
        sort($responseIds);
        sort($dupIds);
        $this->assertEquals($dupIds, $responseIds);
    }

    /**
     * Test global duplicate stats.
     */
    public function test_duplicate_stats(): void
    {
        $date = now()->format('Y-m-d');

        // Insert OCC duplicate (3 records of 1.500 DT each) -> total 4.5 DT, revenue to fix is 3.0 DT
        for ($i = 0; $i < 3; $i++) {
            DB::table('ra_t_occ_cdr_detail')->insert([
                'a_msisdn' => '21699222333',
                'b_msisdn' => '21688888888',
                'start_date' => $date,
                'start_hour' => 14,
                'charge_amount' => 1.500,
                'keyword' => 'SMS2',
                'call_type' => 'VAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insert MMG duplicate (2 records)
        for ($i = 0; $i < 2; $i++) {
            DB::table('ra_t_mmg_cdr_det')->insert([
                'a_msisdn' => '21699333444',
                'b_msisdn' => '21677777777',
                'start_date' => $date,
                'start_hour' => 11,
                'event_type' => 'MO',
                'service_type' => 'SMS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Call stats endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->opToken)
            ->getJson("/api/duplicates/stats?date_debut={$date}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['occ']['total_doublons']);
        $this->assertEquals(3, $data['occ']['affected_cdr']);
        $this->assertEquals(3.0, $data['occ']['revenue_impact']);

        $this->assertEquals(1, $data['mmg']['total_doublons']);
        $this->assertEquals(2, $data['mmg']['affected_cdr']);

        $this->assertEquals(5, $data['total_affected']);
        $this->assertEquals(3.0, $data['total_revenue_impact']);
    }

    /**
     * Test manual delete of specific OCC duplicates (ADMIN only).
     */
    public function test_delete_specific_occ_duplicates(): void
    {
        $date = now()->format('Y-m-d');

        // Insert 3 duplicates
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = DB::table('ra_t_occ_cdr_detail')->insertGetId([
                'a_msisdn' => '21699222333',
                'b_msisdn' => '21688888888',
                'start_date' => $date,
                'start_hour' => 14,
                'charge_amount' => 1.500,
                'keyword' => 'SMS2',
                'call_type' => 'VAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Non-admin (OP) should get forbidden/unauthorized when deleting
        $responseOp = $this->withHeader('Authorization', 'Bearer ' . $this->opToken)
            ->postJson("/api/duplicates/supprimer-occ", ['ids' => $ids]);
        $responseOp->assertStatus(403);

        // Admin can delete
        $responseAdmin = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/duplicates/supprimer-occ", ['ids' => $ids]);

        $responseAdmin->assertStatus(200);
        $data = $responseAdmin->json();
        $this->assertEquals(2, $data['supprimes']);
        $this->assertEquals(3.0, $data['revenus_corriges']);

        // Check that only 1 record remains in DB
        $count = DB::table('ra_t_occ_cdr_detail')
            ->whereIn('id', $ids)
            ->count();
        $this->assertEquals(1, $count);

        // The remaining one should be the first one (lowest ID)
        $remainingId = DB::table('ra_t_occ_cdr_detail')
            ->whereIn('id', $ids)
            ->value('id');
        $this->assertEquals(min($ids), $remainingId);
    }

    /**
     * Test bulk delete of all OCC duplicates (ADMIN only).
     */
    public function test_delete_all_occ_duplicates(): void
    {
        $date = now()->format('Y-m-d');

        // Group 1: 3 duplicates of 1.00 DT -> 2 should be deleted (2.00 DT fixed)
        $group1 = [];
        for ($i = 0; $i < 3; $i++) {
            $group1[] = DB::table('ra_t_occ_cdr_detail')->insertGetId([
                'a_msisdn' => '21699111111',
                'b_msisdn' => '21688888888',
                'start_date' => $date,
                'start_hour' => 10,
                'charge_amount' => 1.000,
                'keyword' => 'SMS1',
                'call_type' => 'VAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Group 2: 2 duplicates of 2.50 DT -> 1 should be deleted (2.50 DT fixed)
        $group2 = [];
        for ($i = 0; $i < 2; $i++) {
            $group2[] = DB::table('ra_t_occ_cdr_detail')->insertGetId([
                'a_msisdn' => '21699222222',
                'b_msisdn' => '21688888888',
                'start_date' => $date,
                'start_hour' => 12,
                'charge_amount' => 2.500,
                'keyword' => 'SMS2',
                'call_type' => 'VAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Call bulk delete with ADMIN
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/duplicates/supprimer-tous-occ", ['date_debut' => $date]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(3, $data['supprimes']);
        $this->assertEquals(4.5, $data['revenus_corriges']);

        // Check remaining counts: should only keep 1 per group
        $this->assertEquals(1, DB::table('ra_t_occ_cdr_detail')->whereIn('id', $group1)->count());
        $this->assertEquals(1, DB::table('ra_t_occ_cdr_detail')->whereIn('id', $group2)->count());
    }
}
