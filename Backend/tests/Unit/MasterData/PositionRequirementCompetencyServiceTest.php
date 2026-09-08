<?php

namespace Tests\Unit\MasterData;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\PositionRequirementCompetency;
use App\Services\Position\PositionRequirementCompetencyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PositionRequirementCompetencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PositionRequirementCompetencyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            PositionRequirementCompetencyService::class
        );
    }

    private function createPosition(
        ?string $code = null,
        ?string $name = null,
    ): Position {
        $code ??= 'TEST-POS-' . uniqid();
        $name ??= 'Test Position';

        return Position::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => 'Test position.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPositionRequirement(
        ?Position $position = null,
    ): PositionRequirement {
        $position ??= $this->createPosition();

        return PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 1,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 90,
            'description' => 'Test position requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createCompetency(
        string $code = 'COMP-001',
        string $name = 'Communication',
    ): Competency {
        return Competency::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => 'Behavioral',
            'description' => 'Test competency.',
            'status' => 'active',
        ]);
    }

    private function createCompetencyLevel(
        int $level = 3,
        string $name = 'Intermediate',
    ): CompetencyLevel {
        return CompetencyLevel::query()->firstOrCreate(
            [
                'level' => $level,
            ],
            [
                'name' => $name,
                'description' => 'Test competency level.',
                'status' => 'active',
            ],
        );
    }

    private function createRequirementCompetency(
        ?PositionRequirement $positionRequirement = null,
        ?Competency $competency = null,
        ?CompetencyLevel $competencyLevel = null,
        array $attributes = [],
    ): PositionRequirementCompetency {
        $positionRequirement ??= $this->createPositionRequirement();
        $competency ??= $this->createCompetency();
        $competencyLevel ??= $this->createCompetencyLevel();

        return PositionRequirementCompetency::query()->create(
            array_merge([
                'position_requirement_id' => $positionRequirement->id,
                'competency_id' => $competency->id,
                'required_level_id' => $competencyLevel->id,
                'minimum_score' => 70,
                'weight' => 100,
                'is_required' => true,
            ], $attributes),
        );
    }

    public function test_it_can_create_position_requirement_competency(): void
    {
        $positionRequirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();
        $competencyLevel = $this->createCompetencyLevel();

        $result = $this->service->create([
            'position_requirement_id' => $positionRequirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $competencyLevel->id,
            'minimum_score' => 80,
            'weight' => 100,
            'is_required' => true,
        ]);

        $this->assertInstanceOf(
            PositionRequirementCompetency::class,
            $result
        );

        $this->assertSame(
            $positionRequirement->id,
            $result->position_requirement_id
        );

        $this->assertSame(
            $competency->id,
            $result->competency_id
        );

        $this->assertSame(
            $competencyLevel->id,
            $result->required_level_id
        );

        $this->assertSame(
            80.0,
            (float) $result->minimum_score
        );

        $this->assertSame(
            100.0,
            (float) $result->weight
        );

        $this->assertTrue(
            $result->is_required
        );

        $this->assertTrue(
            $result->relationLoaded('positionRequirement')
        );

        $this->assertTrue(
            $result->relationLoaded('competency')
        );

        $this->assertTrue(
            $result->relationLoaded('requiredLevel')
        );

        $this->assertDatabaseHas(
            'position_requirement_competencies',
            [
                'id' => $result->id,
                'position_requirement_id' => $positionRequirement->id,
                'competency_id' => $competency->id,
                'required_level_id' => $competencyLevel->id,
                'minimum_score' => 80,
                'weight' => 100,
                'is_required' => true,
            ]
        );
    }

    public function test_it_can_find_position_requirement_competency_by_id(): void
    {
        $item = $this->createRequirementCompetency();

        $result = $this->service->findById(
            $item->id
        );

        $this->assertInstanceOf(
            PositionRequirementCompetency::class,
            $result
        );

        $this->assertSame(
            $item->id,
            $result->id
        );

        $this->assertTrue(
            $result->relationLoaded('positionRequirement')
        );

        $this->assertTrue(
            $result->relationLoaded('competency')
        );

        $this->assertTrue(
            $result->relationLoaded('requiredLevel')
        );
    }

    public function test_it_throws_exception_when_position_requirement_competency_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->service->findById(999999);
    }

    public function test_it_can_get_competencies_by_position_requirement(): void
    {
        $positionRequirement = $this->createPositionRequirement();

        $competencyOne = $this->createCompetency(
            'COMP-001',
            'Communication',
        );

        $competencyTwo = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyOne,
        );

        $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyTwo,
            attributes: [
                'is_required' => false,
            ],
        );

        $result = $this->service->getByPositionRequirement(
            $positionRequirement->id
        );

        $this->assertCount(2, $result);

        $this->assertSame(
            $competencyOne->id,
            $result[0]->competency_id
        );

        $this->assertTrue(
            $result[0]->relationLoaded('competency')
        );

        $this->assertTrue(
            $result[0]->relationLoaded('requiredLevel')
        );
    }

    public function test_it_can_paginate_position_requirement_competencies(): void
    {
        $positionRequirement = $this->createPositionRequirement();

        $competencyOne = $this->createCompetency(
            'COMP-001',
            'Communication',
        );

        $competencyTwo = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyOne,
        );

        $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyTwo,
        );

        $result = $this->service->paginate(
            perPage: 15
        );

        $this->assertSame(
            2,
            $result->total()
        );

        foreach ($result->items() as $item) {
            $this->assertTrue(
                $item->relationLoaded('positionRequirement')
            );

            $this->assertTrue(
                $item->relationLoaded('competency')
            );

            $this->assertTrue(
                $item->relationLoaded('requiredLevel')
            );
        }
    }

    public function test_it_can_filter_by_position_requirement(): void
    {
        $requirementOne = $this->createPositionRequirement();

        $positionTwo = $this->createPosition(
            'MANAGER',
            'Manager',
        );

        $requirementTwo = $this->createPositionRequirement(
            $positionTwo
        );

        $this->createRequirementCompetency(
            positionRequirement: $requirementOne,
            competency: $this->createCompetency(
                'COMP-001',
                'Communication',
            ),
        );

        $this->createRequirementCompetency(
            positionRequirement: $requirementTwo,
            competency: $this->createCompetency(
                'COMP-002',
                'Leadership',
            ),
        );

        $result = $this->service->paginate(
            perPage: 15,
            positionRequirementId: $requirementOne->id,
        );

        $this->assertSame(
            1,
            $result->total()
        );

        $this->assertSame(
            $requirementOne->id,
            $result->items()[0]->position_requirement_id
        );
    }

    public function test_it_can_filter_by_competency(): void
    {
        $competency = $this->createCompetency(
            'COMP-001',
            'Communication',
        );

        $otherCompetency = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $this->createRequirementCompetency(
            competency: $competency,
        );

        $this->createRequirementCompetency(
            competency: $otherCompetency,
        );

        $result = $this->service->paginate(
            perPage: 15,
            competencyId: $competency->id,
        );

        $this->assertSame(
            1,
            $result->total()
        );

        $this->assertSame(
            $competency->id,
            $result->items()[0]->competency_id
        );
    }

    public function test_it_can_filter_by_required_status(): void
    {
        $this->createRequirementCompetency(
            attributes: [
                'is_required' => true,
            ],
        );

        $secondRequirement = $this->createPositionRequirement();

        $secondCompetency = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $this->createRequirementCompetency(
            positionRequirement: $secondRequirement,
            competency: $secondCompetency,
            attributes: [
                'is_required' => false,
            ],
        );

        $requiredResult = $this->service->paginate(
            perPage: 15,
            isRequired: true,
        );

        $optionalResult = $this->service->paginate(
            perPage: 15,
            isRequired: false,
        );

        $this->assertSame(
            1,
            $requiredResult->total()
        );

        $this->assertSame(
            1,
            $optionalResult->total()
        );

        $this->assertTrue(
            $requiredResult->items()[0]->is_required
        );

        $this->assertFalse(
            $optionalResult->items()[0]->is_required
        );
    }

    public function test_it_rejects_duplicate_competency_in_same_position_requirement(): void
    {
        $positionRequirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
        );

        $this->expectException(
            ValidationException::class
        );

        $this->service->create([
            'position_requirement_id' => $positionRequirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => null,
            'minimum_score' => 80,
            'weight' => 50,
            'is_required' => true,
        ]);
    }

    public function test_it_can_update_position_requirement_competency(): void
    {
        $item = $this->createRequirementCompetency();

        $newCompetency = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $newLevel = $this->createCompetencyLevel(
            4,
            'Advanced',
        );

        $result = $this->service->update(
            $item,
            [
                'competency_id' => $newCompetency->id,
                'required_level_id' => $newLevel->id,
                'minimum_score' => 85,
                'weight' => 80,
                'is_required' => false,
            ],
        );

        $this->assertSame(
            $newCompetency->id,
            $result->competency_id
        );

        $this->assertSame(
            $newLevel->id,
            $result->required_level_id
        );

        $this->assertSame(
            85.0,
            (float) $result->minimum_score
        );

        $this->assertSame(
            80.0,
            (float) $result->weight
        );

        $this->assertFalse(
            $result->is_required
        );

        $this->assertDatabaseHas(
            'position_requirement_competencies',
            [
                'id' => $item->id,
                'competency_id' => $newCompetency->id,
                'required_level_id' => $newLevel->id,
                'minimum_score' => 85,
                'weight' => 80,
                'is_required' => false,
            ]
        );
    }

    public function test_it_rejects_duplicate_competency_when_updating(): void
    {
        $positionRequirement = $this->createPositionRequirement();

        $competencyOne = $this->createCompetency(
            'COMP-001',
            'Communication',
        );

        $competencyTwo = $this->createCompetency(
            'COMP-002',
            'Leadership',
        );

        $itemOne = $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyOne,
        );

        $itemTwo = $this->createRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competencyTwo,
        );

        $this->expectException(
            ValidationException::class
        );

        $this->service->update(
            $itemTwo,
            [
                'competency_id' => $itemOne->competency_id,
            ],
        );
    }

    public function test_it_can_delete_position_requirement_competency(): void
    {
        $item = $this->createRequirementCompetency();

        $this->service->delete($item);

        $this->assertDatabaseMissing(
            'position_requirement_competencies',
            [
                'id' => $item->id,
            ]
        );
    }
}
