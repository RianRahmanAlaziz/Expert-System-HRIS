<?php

namespace Tests\Unit\Career;

use App\Models\ActivityLog;
use App\Models\CareerPath;
use App\Models\CareerPathPosition;
use App\Models\Position;
use App\Services\Career\CareerPathPositionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareerPathPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CareerPathPositionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            CareerPathPositionService::class,
        );
    }

    private function createCareerPath(
        ?string $name = null,
    ): CareerPath {
        return CareerPath::query()->create([
            'name' => $name ?? 'Test Career Path',
            'description' => 'Test career path.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        ?string $code = null,
        ?string $name = null,
    ): Position {
        return Position::query()->create([
            'code' => $code ?? 'POS-' . uniqid(),
            'name' => $name ?? 'Test Position',
            'description' => 'Test position.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createCareerPathPosition(
        ?CareerPath $careerPath = null,
        ?Position $position = null,
        array $attributes = [],
    ): CareerPathPosition {
        $careerPath ??= $this->createCareerPath();
        $position ??= $this->createPosition();

        return CareerPathPosition::query()->create(
            array_merge([
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 1,
            ], $attributes),
        );
    }

    public function test_it_can_create_career_path_position(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $result = $this->service->create([
            'career_path_id' => $careerPath->id,
            'position_id' => $position->id,
            'sequence' => 1,
        ]);

        $this->assertInstanceOf(
            CareerPathPosition::class,
            $result,
        );

        $this->assertSame(
            $careerPath->id,
            $result->career_path_id,
        );

        $this->assertSame(
            $position->id,
            $result->position_id,
        );

        $this->assertSame(
            1,
            $result->sequence,
        );

        $this->assertTrue(
            $result->relationLoaded('careerPath'),
        );

        $this->assertTrue(
            $result->relationLoaded('position'),
        );

        $this->assertDatabaseHas(
            'career_path_positions',
            [
                'id' => $result->id,
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 1,
            ],
        );
    }

    public function test_it_can_find_career_path_position_by_id(): void
    {
        $item = $this->createCareerPathPosition();

        $result = $this->service->findById(
            $item->id,
        );

        $this->assertInstanceOf(
            CareerPathPosition::class,
            $result,
        );

        $this->assertSame(
            $item->id,
            $result->id,
        );

        $this->assertTrue(
            $result->relationLoaded('careerPath'),
        );

        $this->assertTrue(
            $result->relationLoaded('position'),
        );
    }

    public function test_it_throws_exception_when_career_path_position_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->findById(999999);
    }

    public function test_it_can_get_positions_by_career_path(): void
    {
        $careerPath = $this->createCareerPath();

        $positionOne = $this->createPosition(
            code: 'POS-001',
            name: 'Staff',
        );

        $positionTwo = $this->createPosition(
            code: 'POS-002',
            name: 'Senior Staff',
        );

        $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionTwo,
            attributes: [
                'sequence' => 2,
            ],
        );

        $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionOne,
            attributes: [
                'sequence' => 1,
            ],
        );

        $result = $this->service->findByCareerPathId(
            $careerPath->id,
        );

        $this->assertCount(
            2,
            $result,
        );

        $this->assertSame(
            $positionOne->id,
            $result[0]->position_id,
        );

        $this->assertSame(
            $positionTwo->id,
            $result[1]->position_id,
        );

        $this->assertTrue(
            $result[0]->relationLoaded('position'),
        );

        $this->assertTrue(
            $result[1]->relationLoaded('position'),
        );
    }

    public function test_it_can_paginate_career_path_positions(): void
    {
        $careerPath = $this->createCareerPath();

        $positionOne = $this->createPosition(
            'Staff',
        );

        $positionTwo = $this->createPosition(
            'Senior Staff',
        );

        $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionOne,
            attributes: [
                'sequence' => 1,
            ],
        );

        $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionTwo,
            attributes: [
                'sequence' => 2,
            ],
        );

        $result = $this->service->paginate(
            perPage: 15,
        );

        $this->assertSame(
            2,
            $result->total(),
        );

        foreach ($result->items() as $item) {
            $this->assertTrue(
                $item->relationLoaded('careerPath'),
            );

            $this->assertTrue(
                $item->relationLoaded('position'),
            );
        }
    }

    public function test_it_can_filter_by_career_path(): void
    {
        $careerPathOne = $this->createCareerPath(
            'HR Career',
        );

        $careerPathTwo = $this->createCareerPath(
            'IT Career',
        );

        $this->createCareerPathPosition(
            careerPath: $careerPathOne,
        );

        $this->createCareerPathPosition(
            careerPath: $careerPathTwo,
        );

        $result = $this->service->paginate(
            perPage: 15,
            careerPathId: $careerPathOne->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $careerPathOne->id,
            $result->items()[0]->career_path_id,
        );
    }

    public function test_it_can_filter_by_position(): void
    {
        $positionOne = $this->createPosition(
            'Staff',
        );

        $positionTwo = $this->createPosition(
            'Senior Staff',
        );

        $this->createCareerPathPosition(
            position: $positionOne,
        );

        $this->createCareerPathPosition(
            position: $positionTwo,
        );

        $result = $this->service->paginate(
            perPage: 15,
            positionId: $positionOne->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $positionOne->id,
            $result->items()[0]->position_id,
        );
    }

    public function test_it_rejects_duplicate_position_in_same_career_path(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $position,
        );

        $this->expectException(
            ValidationException::class,
        );

        $this->service->create([
            'career_path_id' => $careerPath->id,
            'position_id' => $position->id,
            'sequence' => 2,
        ]);
    }

    public function test_it_can_update_career_path_position(): void
    {
        $careerPath = $this->createCareerPath();

        $positionOne = $this->createPosition(
            'Staff',
        );

        $positionTwo = $this->createPosition(
            'Senior Staff',
        );

        $item = $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionOne,
        );

        $result = $this->service->update(
            $item,
            [
                'position_id' => $positionTwo->id,
                'sequence' => 2,
            ],
        );

        $this->assertSame(
            $positionTwo->id,
            $result->position_id,
        );

        $this->assertSame(
            2,
            $result->sequence,
        );

        $this->assertTrue(
            $result->relationLoaded('careerPath'),
        );

        $this->assertTrue(
            $result->relationLoaded('position'),
        );

        $this->assertDatabaseHas(
            'career_path_positions',
            [
                'id' => $item->id,
                'position_id' => $positionTwo->id,
                'sequence' => 2,
            ],
        );
    }

    public function test_it_rejects_duplicate_position_when_updating(): void
    {
        $careerPath = $this->createCareerPath();

        $positionOne = $this->createPosition(
            'Staff',
        );

        $positionTwo = $this->createPosition(
            'Senior Staff',
        );

        $itemOne = $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionOne,
        );

        $itemTwo = $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionTwo,
            attributes: [
                'sequence' => 2,
            ],
        );

        $this->expectException(
            ValidationException::class,
        );

        $this->service->update(
            $itemTwo,
            [
                'position_id' => $itemOne->position_id,
            ],
        );
    }

    public function test_it_can_delete_career_path_position(): void
    {
        $item = $this->createCareerPathPosition();

        $this->service->delete($item);

        $this->assertDatabaseMissing(
            'career_path_positions',
            [
                'id' => $item->id,
            ],
        );
    }

    public function test_it_logs_activity_when_creating_career_path_position(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $item = $this->service->create([
            'career_path_id' => $careerPath->id,
            'position_id' => $position->id,
            'sequence' => 1,
        ]);

        $activityLog = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'career_path_position')
            ->where('target_id', $item->id)
            ->firstOrFail();

        $this->assertSame(
            $careerPath->id,
            $activityLog->new_values['career_path_id'],
        );

        $this->assertSame(
            $position->id,
            $activityLog->new_values['position_id'],
        );

        $this->assertSame(
            1,
            $activityLog->new_values['sequence'],
        );
    }

    public function test_it_logs_activity_when_updating_career_path_position(): void
    {
        $careerPath = $this->createCareerPath();

        $positionOne = $this->createPosition(
            'Staff',
        );

        $positionTwo = $this->createPosition(
            'Senior Staff',
        );

        $item = $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $positionOne,
        );

        $result = $this->service->update(
            $item,
            [
                'position_id' => $positionTwo->id,
                'sequence' => 2,
            ],
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'career_path_position')
            ->where('target_id', $result->id)
            ->firstOrFail();

        $this->assertSame(
            $careerPath->id,
            $activityLog->old_values['career_path_id'],
        );

        $this->assertSame(
            $positionOne->id,
            $activityLog->old_values['position_id'],
        );

        $this->assertSame(
            $positionTwo->id,
            $activityLog->new_values['position_id'],
        );

        $this->assertSame(
            1,
            $activityLog->old_values['sequence'],
        );

        $this->assertSame(
            2,
            $activityLog->new_values['sequence'],
        );
    }

    public function test_it_logs_activity_when_deleting_career_path_position(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $item = $this->createCareerPathPosition(
            careerPath: $careerPath,
            position: $position,
            attributes: [
                'sequence' => 2,
            ],
        );

        $this->service->delete($item);

        $activityLog = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'career_path_position')
            ->where('target_id', $item->id)
            ->firstOrFail();

        $this->assertSame(
            $careerPath->id,
            $activityLog->old_values['career_path_id'],
        );

        $this->assertSame(
            $position->id,
            $activityLog->old_values['position_id'],
        );

        $this->assertSame(
            2,
            $activityLog->old_values['sequence'],
        );
    }
}
