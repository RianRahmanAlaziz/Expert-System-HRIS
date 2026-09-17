<?php

namespace Tests\Unit\MasterData;

use App\Models\ActivityLog;
use App\Models\Position;
use App\Services\Position\PositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class PositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PositionService $positionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->positionService = app(PositionService::class);
    }

    public function test_it_can_create_position(): void
    {
        $position = $this->positionService->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'Human Resources Manager',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Position::class, $position);

        $this->assertSame('HR-MGR', $position->code);
        $this->assertSame('HR Manager', $position->name);
        $this->assertSame(5, $position->level);
        $this->assertTrue($position->is_active);

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'level' => 5,
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_position_by_id(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Developer',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionService->findById(
            $position->id
        );

        $this->assertInstanceOf(Position::class, $result);
        $this->assertSame($position->id, $result->id);
    }

    public function test_it_throws_exception_when_position_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->positionService->findById(999999);
    }

    public function test_it_can_search_position(): void
    {
        Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'Human Resources Manager',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Software Development',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionService->paginate(
            perPage: 15,
            search: 'Developer',
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'Software Developer',
            $result->items()[0]->name
        );
    }

    public function test_it_can_update_position(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Old description',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $result = $this->positionService->update(
            $position,
            [
                'name' => 'Senior Software Developer',
                'description' => 'Updated description',
                'level' => 4,
                'status' => 'inactive',
                'is_active' => false,
            ],
        );

        $this->assertSame(
            'Senior Software Developer',
            $result->name
        );

        $this->assertSame(
            'Updated description',
            $result->description
        );

        $this->assertSame(4, $result->level);
        $this->assertSame('inactive', $result->status);
        $this->assertFalse($result->is_active);

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Senior Software Developer',
            'level' => 4,
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    public function test_it_can_delete_position(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => null,
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->positionService->delete($position);

        $this->assertSoftDeleted('positions', [
            'id' => $position->id,
        ]);
    }

    public function test_it_logs_activity_when_creating_position(): void
    {
        $position = $this->positionService->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'Human Resources Manager',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $activityLog = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'position')
            ->where('target_id', $position->id)
            ->firstOrFail();

        $this->assertSame('HR-MGR', $activityLog->new_values['code']);
        $this->assertSame('HR Manager', $activityLog->new_values['name']);
        $this->assertSame(5, $activityLog->new_values['level']);
        $this->assertSame('active', $activityLog->new_values['status']);
        $this->assertTrue($activityLog->new_values['is_active']);
    }
    public function test_it_logs_activity_when_updating_position(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => 'Old description',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->positionService->update(
            $position,
            [
                'name' => 'Senior Software Developer',
                'description' => 'Updated description',
                'level' => 4,
                'status' => 'inactive',
                'is_active' => false,
            ],
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'position')
            ->where('target_id', $position->id)
            ->firstOrFail();

        $this->assertSame(
            'Software Developer',
            $activityLog->old_values['name']
        );

        $this->assertSame(
            'Senior Software Developer',
            $activityLog->new_values['name']
        );

        $this->assertSame(3, $activityLog->old_values['level']);
        $this->assertSame(4, $activityLog->new_values['level']);

        $this->assertSame('active', $activityLog->old_values['status']);
        $this->assertSame('inactive', $activityLog->new_values['status']);

        $this->assertTrue($activityLog->old_values['is_active']);
        $this->assertFalse($activityLog->new_values['is_active']);
    }

    public function test_it_logs_activity_when_deleting_position(): void
    {
        $position = Position::query()->create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'description' => null,
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->positionService->delete($position);

        $activityLog = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'position')
            ->where('target_id', $position->id)
            ->firstOrFail();

        $this->assertSame('DEV', $activityLog->old_values['code']);
        $this->assertSame(
            'Software Developer',
            $activityLog->old_values['name']
        );
        $this->assertSame(3, $activityLog->old_values['level']);
        $this->assertSame('active', $activityLog->old_values['status']);
        $this->assertTrue($activityLog->old_values['is_active']);
    }
}
