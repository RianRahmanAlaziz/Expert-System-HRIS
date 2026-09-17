<?php

namespace Tests\Unit\Competency;

use App\Models\ActivityLog;
use App\Models\Competency;
use App\Services\Competency\CompetencyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompetencyService $competencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->competencyService = app(CompetencyService::class);
    }

    private function createCompetency(
        string $code = 'COM-001',
        string $name = 'Communication',
        string $category = 'Behavioral',
        ?string $description = 'Communication competency.',
        string $status = 'active',
    ): Competency {
        return Competency::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'status' => $status,
        ]);
    }

    public function test_it_can_create_competency(): void
    {
        $competency = $this->competencyService->create([
            'code' => 'COM-001',
            'name' => 'Communication',
            'category' => 'Behavioral',
            'description' => 'Communication competency.',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            Competency::class,
            $competency
        );

        $this->assertEquals(
            'COM-001',
            $competency->code
        );

        $this->assertEquals(
            'Communication',
            $competency->name
        );

        $this->assertEquals(
            'Behavioral',
            $competency->category
        );

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'code' => 'COM-001',
            'name' => 'Communication',
            'category' => 'Behavioral',
            'status' => 'active',
        ]);
    }

    public function test_it_can_create_competency_without_description(): void
    {
        $competency = $this->competencyService->create([
            'code' => 'TECH-001',
            'name' => 'Problem Solving',
            'category' => 'Technical',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            Competency::class,
            $competency
        );

        $this->assertNull(
            $competency->description
        );

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'code' => 'TECH-001',
            'name' => 'Problem Solving',
            'category' => 'Technical',
            'description' => null,
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_competency_by_id(): void
    {
        $competency = $this->createCompetency();

        $result = $this->competencyService->findById(
            $competency->id
        );

        $this->assertInstanceOf(
            Competency::class,
            $result
        );

        $this->assertEquals(
            $competency->id,
            $result->id
        );

        $this->assertEquals(
            'Communication',
            $result->name
        );
    }

    public function test_it_throws_exception_when_competency_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->competencyService->findById(999999);
    }

    public function test_it_can_paginate_competencies(): void
    {
        $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $this->createCompetency(
            code: 'TECH-001',
            name: 'Problem Solving',
        );

        $result = $this->competencyService->paginate();

        $this->assertCount(
            3,
            $result->items()
        );

        $this->assertEquals(
            3,
            $result->total()
        );
    }

    public function test_it_can_search_competency_by_code(): void
    {
        $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $result = $this->competencyService->paginate(
            search: 'COM-001'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'COM-001',
            $result->items()[0]->code
        );
    }

    public function test_it_can_search_competency_by_name(): void
    {
        $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
        );

        $this->createCompetency(
            code: 'LEAD-001',
            name: 'Leadership',
        );

        $result = $this->competencyService->paginate(
            search: 'Leadership'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'Leadership',
            $result->items()[0]->name
        );
    }

    public function test_it_can_search_competency_by_category(): void
    {
        $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
            category: 'Behavioral',
        );

        $this->createCompetency(
            code: 'TECH-001',
            name: 'Programming',
            category: 'Technical',
        );

        $result = $this->competencyService->paginate(
            search: 'Technical'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'Technical',
            $result->items()[0]->category
        );
    }

    public function test_it_can_search_competency_by_description(): void
    {
        $this->createCompetency(
            code: 'COM-001',
            name: 'Communication',
            description: 'Ability to communicate clearly.',
        );

        $this->createCompetency(
            code: 'TECH-001',
            name: 'Programming',
            description: 'Ability to write clean code.',
        );

        $result = $this->competencyService->paginate(
            search: 'communicate clearly'
        );

        $this->assertCount(
            1,
            $result->items()
        );

        $this->assertEquals(
            'COM-001',
            $result->items()[0]->code
        );
    }

    public function test_it_can_update_competency(): void
    {
        $competency = $this->createCompetency();

        $result = $this->competencyService->update(
            $competency,
            [
                'name' => 'Effective Communication',
                'category' => 'Behavioral',
                'description' => 'Updated description.',
                'status' => 'inactive',
            ]
        );

        $this->assertEquals(
            'Effective Communication',
            $result->name
        );

        $this->assertEquals(
            'inactive',
            $result->status
        );

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'name' => 'Effective Communication',
            'description' => 'Updated description.',
            'status' => 'inactive',
        ]);
    }

    public function test_it_can_delete_competency(): void
    {
        $competency = $this->createCompetency();

        $this->competencyService->delete(
            $competency
        );

        $this->assertDatabaseMissing('competencies', [
            'id' => $competency->id,
        ]);
    }

    public function test_it_logs_activity_when_creating_competency(): void
    {
        $competency = $this->competencyService->create([
            'code' => 'COM-001',
            'name' => 'Communication',
            'category' => 'Behavioral',
            'description' => 'Communication competency.',
            'status' => 'active',
        ]);

        $activityLog = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'competency')
            ->where('target_id', $competency->id)
            ->firstOrFail();

        $this->assertSame(
            'COM-001',
            $activityLog->new_values['code']
        );

        $this->assertSame(
            'Communication',
            $activityLog->new_values['name']
        );

        $this->assertSame(
            'Behavioral',
            $activityLog->new_values['category']
        );

        $this->assertSame(
            'active',
            $activityLog->new_values['status']
        );
    }

    public function test_it_logs_activity_when_updating_competency(): void
    {
        $competency = $this->createCompetency();

        $this->competencyService->update(
            $competency,
            [
                'name' => 'Effective Communication',
                'category' => 'Behavioral',
                'description' => 'Updated description.',
                'status' => 'inactive',
            ]
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'competency')
            ->where('target_id', $competency->id)
            ->firstOrFail();

        $this->assertSame(
            'Communication',
            $activityLog->old_values['name']
        );

        $this->assertSame(
            'Effective Communication',
            $activityLog->new_values['name']
        );

        $this->assertSame(
            'active',
            $activityLog->old_values['status']
        );

        $this->assertSame(
            'inactive',
            $activityLog->new_values['status']
        );

        $this->assertSame(
            'Communication competency.',
            $activityLog->old_values['description']
        );

        $this->assertSame(
            'Updated description.',
            $activityLog->new_values['description']
        );
    }

    public function test_it_logs_activity_when_deleting_competency(): void
    {
        $competency = $this->createCompetency();

        $this->competencyService->delete($competency);

        $activityLog = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'competency')
            ->where('target_id', $competency->id)
            ->firstOrFail();

        $this->assertSame(
            'COM-001',
            $activityLog->old_values['code']
        );

        $this->assertSame(
            'Communication',
            $activityLog->old_values['name']
        );

        $this->assertSame(
            'Behavioral',
            $activityLog->old_values['category']
        );

        $this->assertSame(
            'active',
            $activityLog->old_values['status']
        );
    }
}
