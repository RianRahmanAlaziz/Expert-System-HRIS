<?php

namespace Tests\Unit\SystemSupport;

use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\SystemSupport\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentService::class);

        Storage::fake('public');
    }

    private function createDepartment(
        string $code = 'HR',
    ): Department {
        return Department::query()->create([
            'code' => $code,
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        string $code = 'STAFF',
    ): Position {
        return Position::query()->create([
            'code' => $code,
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        string $employeeNumber = 'EMP-001',
        ?Department $department = null,
        ?Position $position = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    public function test_it_can_upload_document(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $document = $this->service->upload(
            employeeId: $employee->id,
            uploadedBy: $user->id,
            file: $file,
            name: 'KTP',
            description: 'Kartu Tanda Penduduk',
        );

        $this->assertInstanceOf(Document::class, $document);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'name' => 'KTP',
            'file_name' => 'ktp.pdf',
            'mime_type' => 'application/pdf',
            'description' => 'Kartu Tanda Penduduk',
        ]);

        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_it_can_paginate_documents(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        foreach (range(1, 20) as $index) {
            Document::query()->create([
                'employee_id' => $employee->id,
                'uploaded_by' => $user->id,
                'name' => "Document {$index}",
                'file_name' => "document-{$index}.pdf",
                'file_path' => "documents/document-{$index}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'description' => "Description {$index}",
            ]);
        }

        $result = $this->service->paginate(
            perPage: 10,
            employeeId: $employee->id,
        );

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_it_can_search_documents(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        Document::query()->create([
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'name' => 'Kartu Tanda Penduduk',
            'file_name' => 'ktp.pdf',
            'file_path' => 'documents/ktp.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        Document::query()->create([
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'name' => 'Ijazah',
            'file_name' => 'ijazah.pdf',
            'file_path' => 'documents/ijazah.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $result = $this->service->paginate(
            search: 'KTP',
        );

        $this->assertCount(1, $result->items());
        $this->assertEquals(
            'Kartu Tanda Penduduk',
            $result->items()[0]->name,
        );
    }

    public function test_it_can_find_document_by_id(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $document = Document::query()->create([
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'name' => 'Document',
            'file_name' => 'document.pdf',
            'file_path' => 'documents/document.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $result = $this->service->findById(
            $document->id,
        );

        $this->assertEquals(
            $document->id,
            $result->id,
        );

        $this->assertTrue(
            $result->relationLoaded('employee'),
        );

        $this->assertTrue(
            $result->relationLoaded('uploader'),
        );
    }

    public function test_it_can_get_download_path(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'document.pdf',
            100,
            'application/pdf',
        );

        $document = $this->service->upload(
            employeeId: $employee->id,
            uploadedBy: $user->id,
            file: $file,
            name: 'Document',
        );

        $path = $this->service->getDownloadPath(
            $document,
        );

        $this->assertEquals(
            Storage::disk('public')->path(
                $document->file_path,
            ),
            $path,
        );
    }

    public function test_it_can_delete_document_and_file(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'document.pdf',
            100,
            'application/pdf',
        );

        $document = $this->service->upload(
            employeeId: $employee->id,
            uploadedBy: $user->id,
            file: $file,
            name: 'Document',
        );

        $filePath = $document->file_path;
        $documentId = $document->id;

        Storage::disk('public')->assertExists($filePath);

        $this->service->delete($document);

        $this->assertDatabaseMissing('documents', [
            'id' => $documentId,
        ]);

        Storage::disk('public')->assertMissing($filePath);
    }
}
