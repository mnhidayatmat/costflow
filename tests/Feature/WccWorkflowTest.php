<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WccRecord;
use App\Notifications\WccDecided;
use App\Notifications\WccSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WccWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function engineer(): User
    {
        return User::factory()->engineer()->create();
    }

    private function management(): User
    {
        return User::factory()->management()->create();
    }

    private function it(): User
    {
        return User::factory()->it()->create();
    }

    private function record(User $owner, string $status = WccRecord::DRAFT): WccRecord
    {
        $record = WccRecord::factory()->create(['created_by' => $owner->id]);
        $record->forceFill(['status' => $status])->save();

        return $record;
    }

    /* ------------------------------------------------------------ Happy path */

    public function test_an_engineer_walks_a_record_from_draft_to_submitted(): void
    {
        Notification::fake();

        $engineer = $this->engineer();
        $record = $this->record($engineer);

        $this->actingAs($engineer)
            ->post(route('records.transition', $record), ['to' => WccRecord::COSTED])
            ->assertRedirect();

        $this->assertSame(WccRecord::COSTED, $record->refresh()->status);

        $this->actingAs($engineer)
            ->post(route('records.transition', $record), ['to' => WccRecord::SUBMITTED]);

        $this->assertSame(WccRecord::SUBMITTED, $record->refresh()->status);
        $this->assertDatabaseCount('wcc_status_histories', 2);
    }

    public function test_management_approves_a_submitted_record(): void
    {
        Notification::fake();

        $engineer = $this->engineer();
        $manager = $this->management();
        $record = $this->record($engineer, WccRecord::SUBMITTED);

        $this->actingAs($manager)
            ->post(route('records.transition', $record), ['to' => WccRecord::APPROVED])
            ->assertRedirect();

        $this->assertSame(WccRecord::APPROVED, $record->refresh()->status);
        Notification::assertSentTo($engineer, WccDecided::class);
    }

    public function test_submitting_notifies_management_only(): void
    {
        Notification::fake();

        $engineer = $this->engineer();
        $manager = $this->management();
        $other = $this->engineer();
        $record = $this->record($engineer, WccRecord::COSTED);

        $this->actingAs($engineer)->post(route('records.transition', $record), ['to' => WccRecord::SUBMITTED]);

        Notification::assertSentTo($manager, WccSubmitted::class);
        Notification::assertNotSentTo($other, WccSubmitted::class);
    }

    /* -------------------------------------------------------- Authorization */

    public function test_an_engineer_cannot_approve(): void
    {
        $engineer = $this->engineer();
        $record = $this->record($engineer, WccRecord::SUBMITTED);

        $this->actingAs($engineer)
            ->post(route('records.transition', $record), ['to' => WccRecord::APPROVED])
            ->assertForbidden();

        $this->assertSame(WccRecord::SUBMITTED, $record->refresh()->status);
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        $engineer = $this->engineer();
        $record = $this->record($engineer, WccRecord::DRAFT);

        // Draft may only become Costed — never Approved directly.
        $this->actingAs($this->management())
            ->post(route('records.transition', $record), ['to' => WccRecord::APPROVED])
            ->assertForbidden();

        $this->assertSame(WccRecord::DRAFT, $record->refresh()->status);
    }

    public function test_an_approved_record_is_frozen(): void
    {
        $engineer = $this->engineer();
        $record = $this->record($engineer, WccRecord::APPROVED);
        $originalQuo = $record->quo_no;
        $originalSelling = $record->selling;

        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), [
                'quo_no' => 'HACKED', 'client' => 'x', 'title' => 'x',
                'planned_cost' => 1, 'selling' => 999999, 'actual' => 0,
                'version' => $record->version,
            ])
            ->assertForbidden();

        $record->refresh();
        $this->assertSame($originalQuo, $record->quo_no);
        $this->assertSame($originalSelling, $record->selling);
    }

    public function test_a_submitted_record_cannot_be_edited_while_under_review(): void
    {
        $engineer = $this->engineer();
        $record = $this->record($engineer, WccRecord::SUBMITTED);

        $this->actingAs($engineer)
            ->putJson(route('wcc.update', $record), [
                'quo_no' => 'X', 'client' => 'x', 'title' => 'x',
                'planned_cost' => 1, 'selling' => 1, 'actual' => 0,
                'version' => $record->version,
            ])
            ->assertForbidden();
    }

    public function test_an_engineer_cannot_edit_someone_elses_record(): void
    {
        $owner = $this->engineer();
        $intruder = $this->engineer();
        $record = $this->record($owner, WccRecord::DRAFT);

        $this->actingAs($intruder)
            ->putJson(route('wcc.update', $record), [
                'quo_no' => 'X', 'client' => 'x', 'title' => 'x',
                'planned_cost' => 1, 'selling' => 1, 'actual' => 0,
                'version' => $record->version,
            ])
            ->assertForbidden();
    }

    public function test_only_it_may_delete_a_record(): void
    {
        $engineer = $this->engineer();
        $record = $this->record($engineer);

        $this->actingAs($engineer)->delete(route('records.destroy', $record))->assertForbidden();
        $this->assertDatabaseHas('wcc_records', ['id' => $record->id]);

        $this->actingAs($this->it())->delete(route('records.destroy', $record))->assertRedirect();
        $this->assertDatabaseMissing('wcc_records', ['id' => $record->id]);
    }

    public function test_only_it_reaches_user_management(): void
    {
        $this->actingAs($this->engineer())->get('/users')->assertForbidden();
        $this->actingAs($this->management())->get('/users')->assertForbidden();
        $this->actingAs($this->it())->get('/users')->assertOk();
    }

    /* --------------------------------------------------------------- Saving */

    public function test_saving_the_workspace_creates_a_record_with_its_snapshot(): void
    {
        $engineer = $this->engineer();
        $snapshot = json_encode(['fields' => ['w1-quo' => 'BPE-Q-4242'], 'sec' => []]);

        $this->actingAs($engineer)
            ->postJson(route('wcc.store'), [
                'quo_no' => 'BPE-Q-4242',
                'client' => 'Petronas Carigali',
                'title' => 'Subsea survey',
                'dept' => 'Subsea Cable',
                'manager' => 'azwan',
                'planned_cost' => 10000,
                'selling' => 14500,
                'actual' => 0,
                'snapshot' => $snapshot,
            ])
            ->assertCreated()
            ->assertJsonPath('record.quo_no', 'BPE-Q-4242');

        $this->assertDatabaseHas('wcc_records', [
            'quo_no' => 'BPE-Q-4242',
            'manager' => 'AZWAN', // upper-cased on the way in
            'status' => WccRecord::DRAFT,
            'created_by' => $engineer->id,
            'snapshot' => $snapshot,
        ]);
    }

    public function test_a_snapshot_that_is_not_json_is_rejected(): void
    {
        $this->actingAs($this->engineer())
            ->postJson(route('wcc.store'), [
                'quo_no' => 'BPE-Q-1', 'client' => 'c', 'title' => 't',
                'planned_cost' => 1, 'selling' => 1, 'actual' => 0,
                'snapshot' => 'not json {',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('snapshot');

        $this->assertDatabaseCount('wcc_records', 0);
    }

    public function test_management_cannot_create_records(): void
    {
        $this->actingAs($this->management())->get(route('wcc.create'))->assertForbidden();
    }

    /* -------------------------------------------------------------- Metrics */

    public function test_profit_uses_actual_cost_once_wcc2_is_filled_in(): void
    {
        $record = WccRecord::factory()->create([
            'planned_cost' => 10000,
            'selling' => 20000,
            'actual' => 0,
        ]);

        $this->assertSame(10000.0, $record->profit());

        $record->forceFill(['actual' => 12000])->save();

        $this->assertSame(8000.0, $record->fresh()->profit());
        $this->assertSame(40.0, round($record->fresh()->marginPercent(), 1));
    }
}
