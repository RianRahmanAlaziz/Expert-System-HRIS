<?php

namespace Tests\Unit\MasterData;

use App\Models\Position;
use App\Models\PositionRequirement;
use App\Services\Position\PositionRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class PositionRequirementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PositionRequirementService $positionRequirementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->positionRequirementService = app(
            PositionRequirementService::class
        );
    }

    public function test_it_can_create_position_requirement(): void
    {
        $position = Position::query()->create([
            'code' => 'SSE',
            'name' => 'Senior Software Engineer',
            'description' => 'Senior Software Engineer',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $positionRequirement = $this->positionRequirementService->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 3,
            'minimum_performance_score' => 80,
            'minimum_attendance_percentage' => 90,
            'description' => 'Requirement for Senior Software Engineer.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(
            PositionRequirement::class,
            $positionRequirement,
        );

        $this->assertSame(
            $position->id,
            $positionRequirement->position_id,
        );

        $this->assertSame(
            '3.00',
            $positionRequirement->minimum_experience_years,
        );

        $this->assertSame(
            '80.00',
            $positionRequirement->minimum_performance_score,
        );

        $this->assertSame(
            '90.00',
            $positionRequirement->minimum_attendance_percentage,
        );

        $this->assertTrue(
            $positionRequirement->is_active,
        );

        $this->assertDatabaseHas('position_requirements', [
            'id' => $positionRequirement->id,
            'position_id' => $position->id,
            'minimum_experience_years' => 3,
            'minimum_performance_score' => 80,
            'minimum_attendance_percentage' => 90,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_it_can_find_position_requirement_by_id(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Developer',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $positionRequirement = PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2,
            'minimum_performance_score' => 75,
            'minimum_attendance_percentage' => 85,
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->findById(
            $positionRequirement->id,
        );

        $this->assertInstanceOf(
            PositionRequirement::class,
            $result,
        );

        $this->assertSame(
            $positionRequirement->id,
            $result->id,
        );

        $this->assertTrue(
            $result->relationLoaded('position'),
        );

        $this->assertSame(
            $position->id,
            $result->position->id,
        );
    }

    public function test_it_throws_exception_when_position_requirement_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->positionRequirementService->findById(
            999999,
        );
    }

    public function test_it_can_find_active_requirement_by_position(): void
    {
        $position = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'Human Resources Manager',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 80,
            'description' => 'Inactive requirement.',
            'status' => 'inactive',
            'is_active' => false,
        ]);

        $activeRequirement = PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 5,
            'minimum_performance_score' => 85,
            'minimum_attendance_percentage' => 90,
            'description' => 'Active requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->findByPositionId(
            $position->id,
        );

        $this->assertNotNull($result);

        $this->assertSame(
            $activeRequirement->id,
            $result->id,
        );

        $this->assertTrue(
            $result->is_active,
        );
    }

    public function test_it_returns_null_when_position_has_no_active_requirement(): void
    {
        $position = Position::query()->create([
            'code' => 'STAFF',
            'name' => 'Staff',
            'description' => 'Staff',
            'level' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->findByPositionId(
            $position->id,
        );

        $this->assertNull($result);
    }

    public function test_it_can_search_position_requirements_by_position(): void
    {
        $seniorPosition = Position::query()->create([
            'code' => 'SSE',
            'name' => 'Senior Software Engineer',
            'description' => 'Senior Software Engineer',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $hrPosition = Position::query()->create([
            'code' => 'HR',
            'name' => 'HR Staff',
            'description' => 'Human Resources Staff',
            'level' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        PositionRequirement::query()->create([
            'position_id' => $seniorPosition->id,
            'minimum_experience_years' => 3,
            'minimum_performance_score' => 80,
            'minimum_attendance_percentage' => 90,
            'description' => 'Senior requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);

        PositionRequirement::query()->create([
            'position_id' => $hrPosition->id,
            'minimum_experience_years' => 1,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 80,
            'description' => 'HR requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->paginate(
            perPage: 15,
            search: 'Senior',
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            'Senior Software Engineer',
            $result->items()[0]->position->name,
        );
    }

    public function test_it_can_filter_by_position_id(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Developer',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $anotherPosition = Position::query()->create([
            'code' => 'HR',
            'name' => 'HR Staff',
            'description' => 'HR Staff',
            'level' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2,
            'minimum_performance_score' => 75,
            'minimum_attendance_percentage' => 85,
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        PositionRequirement::query()->create([
            'position_id' => $anotherPosition->id,
            'minimum_experience_years' => 1,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 80,
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->paginate(
            perPage: 15,
            positionId: $position->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $position->id,
            $result->items()[0]->position_id,
        );
    }

    public function test_it_can_update_position_requirement(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Developer',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $positionRequirement = PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 80,
            'description' => 'Old requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionRequirementService->update(
            $positionRequirement,
            [
                'minimum_experience_years' => 4,
                'minimum_performance_score' => 85,
                'minimum_attendance_percentage' => 90,
                'description' => 'Updated requirement.',
                'status' => 'active',
                'is_active' => true,
            ],
        );

        $this->assertSame(
            '4.00',
            $result->minimum_experience_years,
        );

        $this->assertSame(
            '85.00',
            $result->minimum_performance_score,
        );

        $this->assertSame(
            '90.00',
            $result->minimum_attendance_percentage,
        );

        $this->assertSame(
            'Updated requirement.',
            $result->description,
        );

        $this->assertDatabaseHas(
            'position_requirements',
            [
                'id' => $positionRequirement->id,
                'minimum_experience_years' => 4,
                'minimum_performance_score' => 85,
                'minimum_attendance_percentage' => 90,
                'description' => 'Updated requirement.',
            ],
        );
    }

    public function test_it_can_delete_position_requirement(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Developer',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $positionRequirement = PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2,
            'minimum_performance_score' => 75,
            'minimum_attendance_percentage' => 85,
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->positionRequirementService->delete(
            $positionRequirement,
        );

        $this->assertSoftDeleted(
            'position_requirements',
            [
                'id' => $positionRequirement->id,
            ],
        );
    }
}
