<?php

namespace Tests\Unit\Training;

use App\Models\ActivityLog;
use App\Models\Training;
use App\Services\Training\TrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrainingService $trainingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainingService = app(TrainingService::class);
    }

    public function test_it_can_create_training(): void
    {
        $training = $this->trainingService->create([
            'code' => 'TRN-001',
            'name' => 'Leadership Development',
            'category' => 'Leadership',
            'description' => 'Leadership training.',
            'trainer' => 'HR Development Team',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 30,
            'status' => 'scheduled',
        ]);

        $this->assertInstanceOf(Training::class, $training);

        $this->assertSame('TRN-001', $training->code);
        $this->assertSame(
            'Leadership Development',
            $training->name
        );

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'code' => 'TRN-001',
            'name' => 'Leadership Development',
            'status' => 'scheduled',
        ]);
    }

    public function test_it_can_find_training_by_id(): void
    {
        $training = Training::factory()->create();

        $result = $this->trainingService->findById(
            $training->id
        );

        $this->assertInstanceOf(Training::class, $result);
        $this->assertSame($training->id, $result->id);
    }

    public function test_it_throws_exception_when_training_is_not_found(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->trainingService->findById(999999);
    }

    public function test_it_can_update_training(): void
    {
        $training = Training::factory()->create([
            'name' => 'Old Training Name',
            'status' => 'scheduled',
        ]);

        $result = $this->trainingService->update(
            $training,
            [
                'name' => 'Updated Training Name',
                'status' => 'ongoing',
            ],
        );

        $this->assertSame(
            'Updated Training Name',
            $result->name
        );

        $this->assertSame('ongoing', $result->status);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'name' => 'Updated Training Name',
            'status' => 'ongoing',
        ]);
    }

    public function test_it_can_update_training_status(): void
    {
        $training = Training::factory()->create([
            'status' => 'scheduled',
        ]);

        $result = $this->trainingService->updateStatus(
            $training,
            'ongoing',
        );

        $this->assertSame('ongoing', $result->status);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => 'ongoing',
        ]);
    }

    public function test_it_can_search_training(): void
    {
        Training::factory()->create([
            'code' => 'TRN-001',
            'name' => 'Leadership Development',
            'category' => 'Leadership',
        ]);

        Training::factory()->create([
            'code' => 'TRN-002',
            'name' => 'Communication Skills',
            'category' => 'Communication',
        ]);

        $result = $this->trainingService->paginate(
            perPage: 15,
            search: 'Leadership',
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'Leadership Development',
            $result->items()[0]->name
        );
    }

    public function test_it_can_delete_training(): void
    {
        $training = Training::factory()->create();

        $this->trainingService->delete($training);

        $this->assertDatabaseMissing('trainings', [
            'id' => $training->id,
        ]);
    }

    public function test_it_logs_activity_when_creating_training(): void
    {
        $training = $this->trainingService->create([
            'code' => 'TRN-001',
            'name' => 'Leadership Development',
            'category' => 'Leadership',
            'description' => 'Leadership training.',
            'trainer' => 'HR Development Team',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 30,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create',
            'module' => 'training',
            'target_type' => $training->getMorphClass(),
            'target_id' => $training->id,
        ]);

        $activityLog = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'training')
            ->where('target_id', $training->id)
            ->firstOrFail();

        $this->assertSame(
            'TRN-001',
            $activityLog->new_values['code']
        );

        $this->assertSame(
            'Leadership Development',
            $activityLog->new_values['name']
        );

        $this->assertSame(
            'scheduled',
            $activityLog->new_values['status']
        );

        $this->assertSame(
            30,
            $activityLog->new_values['capacity']
        );
    }

    public function test_it_logs_activity_when_updating_training(): void
    {
        $training = Training::factory()->create([
            'code' => 'TRN-001',
            'name' => 'Old Training Name',
            'category' => 'Leadership',
            'description' => 'Old description.',
            'trainer' => 'Old Trainer',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 20,
            'status' => 'scheduled',
        ]);

        $result = $this->trainingService->update(
            $training,
            [
                'name' => 'Updated Training Name',
                'description' => 'Updated description.',
                'trainer' => 'Updated Trainer',
                'capacity' => 30,
                'status' => 'ongoing',
            ],
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'training')
            ->where('target_id', $result->id)
            ->firstOrFail();

        $this->assertSame(
            'Old Training Name',
            $activityLog->old_values['name']
        );

        $this->assertSame(
            'Updated Training Name',
            $activityLog->new_values['name']
        );

        $this->assertSame(
            'Old description.',
            $activityLog->old_values['description']
        );

        $this->assertSame(
            'Updated description.',
            $activityLog->new_values['description']
        );

        $this->assertSame(
            20,
            $activityLog->old_values['capacity']
        );

        $this->assertSame(
            30,
            $activityLog->new_values['capacity']
        );

        $this->assertSame(
            'scheduled',
            $activityLog->old_values['status']
        );

        $this->assertSame(
            'ongoing',
            $activityLog->new_values['status']
        );
    }

    public function test_it_logs_activity_when_updating_training_status(): void
    {
        $training = Training::factory()->create([
            'status' => 'scheduled',
        ]);

        $result = $this->trainingService->updateStatus(
            $training,
            'ongoing',
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update_status')
            ->where('module', 'training')
            ->where('target_id', $result->id)
            ->firstOrFail();

        $this->assertSame(
            'scheduled',
            $activityLog->old_values['status']
        );

        $this->assertSame(
            'ongoing',
            $activityLog->new_values['status']
        );
    }

    public function test_it_logs_activity_when_deleting_training(): void
    {
        $training = Training::factory()->create([
            'code' => 'TRN-001',
            'name' => 'Leadership Development',
            'category' => 'Leadership',
            'description' => 'Leadership training.',
            'trainer' => 'HR Development Team',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 30,
            'status' => 'scheduled',
        ]);

        $this->trainingService->delete($training);

        $activityLog = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'training')
            ->where('target_id', $training->id)
            ->firstOrFail();

        $this->assertSame(
            'TRN-001',
            $activityLog->old_values['code']
        );

        $this->assertSame(
            'Leadership Development',
            $activityLog->old_values['name']
        );

        $this->assertSame(
            'Leadership',
            $activityLog->old_values['category']
        );

        $this->assertSame(
            30,
            $activityLog->old_values['capacity']
        );

        $this->assertSame(
            'scheduled',
            $activityLog->old_values['status']
        );
    }
}
