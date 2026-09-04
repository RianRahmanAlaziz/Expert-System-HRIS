<?php

namespace Tests\Unit\Training;

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
            $result->first()->name
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
}
