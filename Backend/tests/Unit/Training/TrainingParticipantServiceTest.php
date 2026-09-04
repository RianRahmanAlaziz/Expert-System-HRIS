<?php

namespace Tests\Unit\Training;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Services\Training\TrainingParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingParticipantServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrainingParticipantService $participantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participantService = app(
            TrainingParticipantService::class
        );
    }

    public function test_it_can_register_participant(): void
    {
        $training = Training::factory()->create();

        $employee = Employee::factory()->create();

        $participant = $this->participantService->create([
            'training_id' => $training->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertInstanceOf(
            TrainingParticipant::class,
            $participant
        );

        $this->assertSame(
            $training->id,
            $participant->training_id
        );

        $this->assertSame(
            $employee->id,
            $participant->employee_id
        );

        $this->assertSame(
            'registered',
            $participant->status
        );

        $this->assertNotNull(
            $participant->registered_at
        );

        $this->assertDatabaseHas(
            'training_participants',
            [
                'id' => $participant->id,
                'training_id' => $training->id,
                'employee_id' => $employee->id,
                'status' => 'registered',
            ]
        );
    }

    public function test_it_can_find_participant_by_id(): void
    {
        $participant = TrainingParticipant::factory()->create();

        $result = $this->participantService->findById(
            $participant->id
        );

        $this->assertInstanceOf(
            TrainingParticipant::class,
            $result
        );

        $this->assertSame(
            $participant->id,
            $result->id
        );
    }

    public function test_it_throws_exception_when_participant_is_not_found(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->participantService->findById(999999);
    }

    public function test_it_can_update_participant(): void
    {
        $participant = TrainingParticipant::factory()->create([
            'status' => 'registered',
        ]);

        $result = $this->participantService->update(
            $participant,
            [
                'status' => 'completed',
                'completed_at' => '2026-09-17 16:00:00',
                'certificate_path' =>
                'certificates/training-certificate.pdf',
            ],
        );

        $this->assertSame(
            'completed',
            $result->status
        );

        $this->assertNotNull(
            $result->completed_at
        );

        $this->assertSame(
            'certificates/training-certificate.pdf',
            $result->certificate_path
        );

        $this->assertDatabaseHas(
            'training_participants',
            [
                'id' => $participant->id,
                'status' => 'completed',
                'certificate_path' =>
                'certificates/training-certificate.pdf',
            ]
        );
    }

    public function test_it_can_evaluate_participant(): void
    {
        $participant = TrainingParticipant::factory()->create();

        $result = $this->participantService->evaluate(
            $participant,
            87.5,
        );

        $this->assertSame(
            87.5,
            (float) $result->score
        );

        $this->assertDatabaseHas(
            'training_participants',
            [
                'id' => $participant->id,
                'score' => 87.5,
            ]
        );
    }

    public function test_it_can_get_employee_training_history(): void
    {
        $employee = Employee::factory()->create();

        $trainingOne = Training::factory()->create();
        $trainingTwo = Training::factory()->create();

        TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
            'training_id' => $trainingOne->id,
            'registered_at' => '2026-09-01 10:00:00',
        ]);

        TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
            'training_id' => $trainingTwo->id,
            'registered_at' => '2026-09-02 10:00:00',
        ]);

        $otherEmployee = Employee::factory()->create();

        TrainingParticipant::factory()->create([
            'employee_id' => $otherEmployee->id,
            'training_id' => $trainingOne->id,
        ]);

        $result = $this->participantService->history(
            employeeId: $employee->id,
            perPage: 15,
        );

        $this->assertSame(2, $result->total());

        foreach ($result->items() as $participant) {
            $this->assertSame(
                $employee->id,
                $participant->employee_id
            );

            $this->assertTrue(
                $participant->relationLoaded('training')
            );
        }
    }

    public function test_it_can_filter_participants_by_training(): void
    {
        $training = Training::factory()->create();

        $otherTraining = Training::factory()->create();

        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
        ]);

        TrainingParticipant::factory()->create([
            'training_id' => $otherTraining->id,
        ]);

        $result = $this->participantService->paginate(
            perPage: 15,
            trainingId: $training->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            $training->id,
            $result->first()->training_id
        );
    }

    public function test_it_can_filter_participants_by_employee(): void
    {
        $employee = Employee::factory()->create();

        $otherEmployee = Employee::factory()->create();

        TrainingParticipant::factory()->create([
            'employee_id' => $employee->id,
        ]);

        TrainingParticipant::factory()->create([
            'employee_id' => $otherEmployee->id,
        ]);

        $result = $this->participantService->paginate(
            perPage: 15,
            employeeId: $employee->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            $employee->id,
            $result->first()->employee_id
        );
    }

    public function test_it_can_delete_participant(): void
    {
        $participant = TrainingParticipant::factory()->create();

        $this->participantService->delete($participant);

        $this->assertDatabaseMissing(
            'training_participants',
            [
                'id' => $participant->id,
            ]
        );
    }
}
