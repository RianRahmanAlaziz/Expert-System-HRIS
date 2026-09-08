<?php

namespace Tests\Unit\Career;

use App\Models\CareerPath;
use App\Services\Career\CareerPathService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerPathServiceTest extends TestCase
{
    use RefreshDatabase;

    private CareerPathService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            CareerPathService::class,
        );
    }

    private function createCareerPath(
        ?string $code = null,
        ?string $name = null,
        array $attributes = [],
    ): CareerPath {
        return CareerPath::query()->create(
            array_merge([
                'code' => $code ?? 'CAREER-' . uniqid(),
                'name' => $name ?? 'Test Career Path',
                'description' => 'Test career path.',
                'status' => 'active',
                'is_active' => true,
            ], $attributes),
        );
    }

    public function test_it_can_create_career_path(): void
    {
        $result = $this->service->create([
            'code' => 'HR-CAREER',
            'name' => 'HR Career Track',
            'description' => 'Career path for HR.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(
            CareerPath::class,
            $result,
        );

        $this->assertSame(
            'HR-CAREER',
            $result->code,
        );

        $this->assertSame(
            'HR Career Track',
            $result->name,
        );

        $this->assertSame(
            'Career path for HR.',
            $result->description,
        );

        $this->assertSame(
            'active',
            $result->status,
        );

        $this->assertTrue(
            $result->is_active,
        );

        $this->assertDatabaseHas(
            'career_paths',
            [
                'id' => $result->id,
                'code' => 'HR-CAREER',
                'name' => 'HR Career Track',
                'status' => 'active',
                'is_active' => true,
            ],
        );
    }

    public function test_it_can_find_career_path_by_id(): void
    {
        $careerPath = $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
        );

        $result = $this->service->findById(
            $careerPath->id,
        );

        $this->assertInstanceOf(
            CareerPath::class,
            $result,
        );

        $this->assertSame(
            $careerPath->id,
            $result->id,
        );

        $this->assertSame(
            'HR-CAREER',
            $result->code,
        );
    }

    public function test_it_throws_exception_when_career_path_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->service->findById(999999);
    }

    public function test_it_can_paginate_career_paths(): void
    {
        $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
        );

        $this->createCareerPath(
            'IT-CAREER',
            'IT Career Track',
        );

        $result = $this->service->paginate(
            perPage: 15,
        );

        $this->assertSame(
            2,
            $result->total(),
        );

        foreach ($result->items() as $item) {
            $this->assertInstanceOf(
                CareerPath::class,
                $item,
            );
        }
    }

    public function test_it_can_search_career_paths(): void
    {
        $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
        );

        $this->createCareerPath(
            'IT-CAREER',
            'IT Career Track',
        );

        $result = $this->service->paginate(
            perPage: 15,
            search: 'HR',
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            'HR-CAREER',
            $result->items()[0]->code,
        );
    }

    public function test_it_can_filter_by_status(): void
    {
        $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
            [
                'status' => 'active',
            ],
        );

        $this->createCareerPath(
            'IT-CAREER',
            'IT Career Track',
            [
                'status' => 'inactive',
            ],
        );

        $result = $this->service->paginate(
            perPage: 15,
            status: 'active',
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            'active',
            $result->items()[0]->status,
        );
    }

    public function test_it_can_filter_by_active_status(): void
    {
        $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
            [
                'is_active' => true,
            ],
        );

        $this->createCareerPath(
            'IT-CAREER',
            'IT Career Track',
            [
                'is_active' => false,
            ],
        );

        $activeResult = $this->service->paginate(
            perPage: 15,
            isActive: true,
        );

        $inactiveResult = $this->service->paginate(
            perPage: 15,
            isActive: false,
        );

        $this->assertSame(
            1,
            $activeResult->total(),
        );

        $this->assertSame(
            1,
            $inactiveResult->total(),
        );

        $this->assertTrue(
            $activeResult->items()[0]->is_active,
        );

        $this->assertFalse(
            $inactiveResult->items()[0]->is_active,
        );
    }

    public function test_it_can_update_career_path(): void
    {
        $careerPath = $this->createCareerPath(
            'HR-CAREER',
            'HR Career Track',
        );

        $result = $this->service->update(
            $careerPath,
            [
                'name' => 'Updated HR Career Track',
                'description' => 'Updated description.',
                'status' => 'inactive',
                'is_active' => false,
            ],
        );

        $this->assertSame(
            'Updated HR Career Track',
            $result->name,
        );

        $this->assertSame(
            'Updated description.',
            $result->description,
        );

        $this->assertSame(
            'inactive',
            $result->status,
        );

        $this->assertFalse(
            $result->is_active,
        );

        $this->assertDatabaseHas(
            'career_paths',
            [
                'id' => $careerPath->id,
                'name' => 'Updated HR Career Track',
                'status' => 'inactive',
                'is_active' => false,
            ],
        );
    }

    public function test_it_can_delete_career_path(): void
    {
        $careerPath = $this->createCareerPath();

        $this->service->delete($careerPath);

        $this->assertDatabaseMissing(
            'career_paths',
            [
                'id' => $careerPath->id,
            ],
        );
    }
}
