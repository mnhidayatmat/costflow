<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WccRecord;
use App\Services\WccMetrics;
use App\Services\WccWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecordIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'quo_no' => 'BPE-Q-1000',
            'client' => 'Petronas',
            'title' => 'Job',
            'planned_cost' => 100,
            'selling' => 145,
            'actual' => 0,
        ], $overrides);
    }

    /* ------------------------------------------------- Optimistic locking */

    public function test_a_stale_save_is_rejected_instead_of_overwriting(): void
    {
        $engineer = User::factory()->engineer()->create();
        $record = WccRecord::factory()->create(['created_by' => $engineer->id, 'status' => WccRecord::DRAFT]);

        // Two tabs open the record; both hold version 1.
        $staleVersion = $record->version;

        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), $this->payload(['client' => 'First writer', 'version' => $staleVersion]))
            ->assertOk();

        // The second tab saves against the version it loaded — now out of date.
        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), $this->payload(['client' => 'Second writer', 'version' => $staleVersion]))
            ->assertStatus(409)
            ->assertJsonPath('your_version', $staleVersion)
            ->assertJsonPath('current_version', $staleVersion + 1);

        $this->assertSame('First writer', $record->refresh()->client);
    }

    public function test_a_save_bumps_the_version_and_reports_it_back(): void
    {
        $engineer = User::factory()->engineer()->create();
        $record = WccRecord::factory()->create(['created_by' => $engineer->id, 'status' => WccRecord::DRAFT]);

        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), $this->payload(['version' => 1]))
            ->assertOk()
            ->assertJsonPath('record.version', 2);

        $this->assertSame(2, $record->refresh()->version);
    }

    public function test_a_new_record_starts_at_version_one(): void
    {
        $this->actingAs(User::factory()->engineer()->create())
            ->postJson(route('wcc.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('record.version', 1);
    }

    public function test_updating_without_a_version_is_refused(): void
    {
        $engineer = User::factory()->engineer()->create();
        $record = WccRecord::factory()->create(['created_by' => $engineer->id, 'status' => WccRecord::DRAFT]);

        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    }

    /* ------------------------------------------------------ approved_at */

    public function test_approving_stamps_approved_at(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-07-10 09:00:00');

        $engineer = User::factory()->engineer()->create();
        $manager = User::factory()->management()->create();

        $record = WccRecord::factory()->create(['created_by' => $engineer->id]);
        $record->forceFill(['status' => WccRecord::SUBMITTED])->save();

        $this->assertNull($record->approved_at);

        app(WccWorkflow::class)->transition($record, WccRecord::APPROVED, $manager);

        $this->assertTrue(Carbon::parse('2026-07-10 09:00:00')->equalTo($record->refresh()->approved_at));
    }

    public function test_editing_an_old_record_does_not_move_its_revenue_into_this_month(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        // Approved four months ago, worth RM18,490.
        $old = WccRecord::factory()->approved()->create(['selling' => 18490, 'planned_cost' => 12750, 'actual' => 12300]);
        $old->forceFill(['approved_at' => Carbon::parse('2026-03-14 10:00:00')])->save();

        $metrics = app(WccMetrics::class);
        $before = $metrics->month(0)['win'];

        // Someone re-saves it today — a typo fix, no financial change.
        $old->touch();

        $this->assertSame($before, $metrics->month(0)['win']);
        $this->assertSame(0.0, $metrics->month(0)['win']);
        $this->assertSame(18490.0, $metrics->month(4)['win']);
    }

    public function test_the_monthly_trend_buckets_on_the_approval_date(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        $record = WccRecord::factory()->approved()->create(['selling' => 5000]);
        $record->forceFill(['approved_at' => Carbon::parse('2026-05-02 10:00:00')])->save();
        $record->touch(); // updated_at is now July

        $trend = collect(app(WccMetrics::class)->monthlyTrend(6))->keyBy('label');

        $this->assertSame(5000.0, $trend['May']['value']);
        $this->assertSame(0.0, $trend['Jul']['value']);
    }

    /* ------------------------------------------------------------- 413 */

    public function test_a_body_larger_than_post_max_size_gets_an_honest_413(): void
    {
        $engineer = User::factory()->engineer()->create();

        $limit = (int) (ini_get('post_max_size')[0] ?? 8) * 1024 * 1024;

        // We cannot really ship 10 MB through the test kernel, but the guard
        // reads Content-Length — which is exactly what PHP acts on.
        $response = $this->actingAs($engineer)->call(
            'POST',
            route('wcc.store'),
            [], [], [],
            ['CONTENT_LENGTH' => (string) ($limit + 1), 'HTTP_ACCEPT' => 'application/json'],
        );

        $response->assertStatus(413);
        $this->assertStringContainsString('limit', $response->json('message'));
        $this->assertDatabaseCount('wcc_records', 0);
    }

    public function test_a_normal_sized_body_passes_the_guard(): void
    {
        $this->actingAs(User::factory()->engineer()->create())
            ->postJson(route('wcc.store'), $this->payload())
            ->assertCreated();
    }
}
