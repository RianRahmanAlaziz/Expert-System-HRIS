<?php

namespace Tests\Unit\Training;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\EmployeeService;
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

    private function createUser(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function createEmployee(
        ?User $user = null,
        ?Employee $manager = null
    ): Employee {
        $user ??= $this->createUser();

        $department = Department::firstOrCreate(
            ['code' => 'HR'],
            [
                'name' => 'Human Resources',
                'description' => 'HR Department',
                'status' => 'active',
            ]
        );

        $position = Position::firstOrCreate(
            ['code' => 'STAFF'],
            [
                'name' => 'Staff',
                'description' => 'Staff Position',
                'level' => 1,
                'status' => 'active',
            ]
        );

        $employeeService = app(EmployeeService::class);

        $employeeData = [
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'gender' => 'male',
            'join_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ];

        if ($manager) {
            $employeeData['manager_id'] = $manager->id;
        }

        return $employeeService->create($employeeData);
    }

    private function createParticipant(
        ?Training $training = null,
        ?Employee $employee = null,
        array $attributes = []
    ): TrainingParticipant {
        $training ??= Training::factory()->create();
        $employee ??= $this->createEmployee();

        return TrainingParticipant::query()->create(array_merge([
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'status' => 'registered',
            'score' => null,
            'registered_at' => now(),
            'completed_at' => null,
            'certificate_path' => null,
        ], $attributes));
    }

    public function test_it_can_register_participant(): void
    {
        $training = Training::factory()->create();
        $employee = $this->createEmployee();

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
        $participant = $this->createParticipant();

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
        $participant = $this->createParticipant(
            attributes: [
                'status' => 'registered',
            ],
        );

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
        $participant = $this->createParticipant();

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
        $employee = $this->createEmployee();

        $trainingOne = Training::factory()->create();
        $trainingTwo = Training::factory()->create();

        $this->createParticipant(
            training: $trainingOne,
            employee: $employee,
            attributes: [
                'registered_at' => '2026-09-01 10:00:00',
            ],
        );

        $this->createParticipant(
            training: $trainingTwo,
            employee: $employee,
            attributes: [
                'registered_at' => '2026-09-02 10:00:00',
            ],
        );

        $otherEmployee = $this->createEmployee();

        $this->createParticipant(
            training: $trainingOne,
            employee: $otherEmployee,
        );

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

        $this->createParticipant(
            training: $training,
        );

        $this->createParticipant(
            training: $otherTraining,
        );

        $result = $this->participantService->paginate(
            perPage: 15,
            trainingId: $training->id,
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            $training->id,
            $result->items()[0]->training_id
        );
    }

    public function test_it_can_filter_participants_by_employee(): void
    {
        $employee = $this->createEmployee();

        $otherEmployee = $this->createEmployee();

        $this->createParticipant(
            employee: $employee,
        );

        $this->createParticipant(
            employee: $otherEmployee,
        );

        $result = $this->participantService->paginate(
            perPage: 15,
            employeeId: $employee->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            $employee->id,
            $result->items()[0]->employee_id
        );
    }

    public function test_it_can_delete_participant(): void
    {
        $participant = $this->createParticipant();

        $this->participantService->delete($participant);

        $this->assertDatabaseMissing(
            'training_participants',
            [
                'id' => $participant->id,
            ]
        );
    }
}
