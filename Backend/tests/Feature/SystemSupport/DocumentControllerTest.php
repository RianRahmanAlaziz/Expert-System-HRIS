<?php

namespace Tests\Feature\SystemSupport;

use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\SystemSupport\DocumentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();

        $this->user->assignRole('hr-admin');

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

    private function createDocument(
        Employee $employee,
        ?User $uploadedBy = null,
        array $attributes = [],
    ): Document {
        $uploadedBy ??= $this->user;

        return Document::query()->create([
            'employee_id' => $employee->id,
            'uploaded_by' => $uploadedBy->id,
            'name' => 'KTP',
            'file_name' => 'ktp.pdf',
            'file_path' => 'documents/ktp.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'description' => 'Kartu Tanda Penduduk.',
            ...$attributes,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_index(): void
    {
        $response = $this->getJson('/api/v1/documents');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_index(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/documents');

        $response->assertForbidden();
    }

    public function test_user_can_list_documents(): void
    {
        $employee = $this->createEmployee();

        $document = $this->createDocument($employee);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/documents');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $document->id,
                'employee_id' => $employee->id,
                'name' => 'KTP',
            ]);
    }

    public function test_index_uses_default_pagination(): void
    {
        $employee = $this->createEmployee();

        for ($i = 1; $i <= 3; $i++) {
            $this->createDocument(
                $employee,
                attributes: [
                    'name' => "Document {$i}",
                    'file_name' => "document-{$i}.pdf",
                    'file_path' => "documents/document-{$i}.pdf",
                ],
            );
        }

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/documents');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            );
    }

    public function test_index_can_filter_by_employee(): void
    {
        $employee = $this->createEmployee(
            'EMP-001',
            $this->createDepartment('HR-001'),
            $this->createPosition('STAFF-001'),
        );

        $otherEmployee = $this->createEmployee(
            'EMP-002',
            $this->createDepartment('HR-002'),
            $this->createPosition('STAFF-002'),
        );

        $document = $this->createDocument(
            $employee,
        );

        $this->createDocument(
            $otherEmployee,
            attributes: [
                'name' => 'Ijazah',
                'file_name' => 'ijazah.pdf',
                'file_path' => 'documents/ijazah.pdf',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/documents?employee_id={$employee->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $document->id,
            );
    }

    public function test_index_can_search_documents(): void
    {
        $employee = $this->createEmployee();

        $document = $this->createDocument(
            $employee,
            attributes: [
                'name' => 'Kartu Tanda Penduduk',
                'file_name' => 'ktp.pdf',
            ],
        );

        $this->createDocument(
            $employee,
            attributes: [
                'name' => 'Ijazah',
                'file_name' => 'ijazah.pdf',
                'file_path' => 'documents/ijazah.pdf',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/documents?search=KTP',
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $document->id,
            );
    }

    public function test_user_without_create_permission_cannot_upload_document(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/documents',
                [
                    'employee_id' => $employee->id,
                    'name' => 'KTP',
                    'file' => $file,
                ],
            );

        $response->assertForbidden();
    }

    public function test_user_can_upload_document(): void
    {
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $response = $this
            ->actingAs($this->user)
            ->post(
                '/api/v1/documents',
                [
                    'employee_id' => $employee->id,
                    'name' => 'KTP',
                    'file' => $file,
                    'description' => 'Kartu Tanda Penduduk.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'employee_id' => $employee->id,
                'name' => 'KTP',
                'file_name' => 'ktp.pdf',
            ]);

        $this->assertDatabaseHas('documents', [
            'employee_id' => $employee->id,
            'uploaded_by' => $this->user->id,
            'name' => 'KTP',
            'file_name' => 'ktp.pdf',
        ]);
    }

    public function test_upload_requires_employee(): void
    {
        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $response = $this
            ->actingAs($this->user)
            ->post(
                '/api/v1/documents',
                [
                    'name' => 'KTP',
                    'file' => $file,
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'employee_id',
        ]);
    }

    public function test_upload_requires_name(): void
    {
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $response = $this
            ->actingAs($this->user)
            ->post(
                '/api/v1/documents',
                [
                    'employee_id' => $employee->id,
                    'file' => $file,
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_upload_requires_file(): void
    {
        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($this->user)
            ->post(
                '/api/v1/documents',
                [
                    'employee_id' => $employee->id,
                    'name' => 'KTP',
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'document.exe',
            100,
            'application/octet-stream',
        );

        $response = $this
            ->actingAs($this->user)
            ->post(
                '/api/v1/documents',
                [
                    'employee_id' => $employee->id,
                    'name' => 'Document',
                    'file' => $file,
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);
    }

    public function test_user_can_show_document(): void
    {
        $employee = $this->createEmployee();

        $document = $this->createDocument($employee);

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/documents/{$document->id}");

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $document->id,
                'employee_id' => $employee->id,
                'name' => 'KTP',
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/documents/999999');

        $response->assertNotFound();
    }

    public function test_user_can_download_document(): void
    {
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $document = app(DocumentService::class)->upload(
            employeeId: $employee->id,
            uploadedBy: $this->user->id,
            file: $file,
            name: 'KTP',
        );

        $response = $this
            ->actingAs($this->user)
            ->get("/api/v1/documents/{$document->id}/download");

        $response
            ->assertOk()
            ->assertDownload($document->file_name);
    }

    public function test_user_without_delete_permission_cannot_delete_document(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();
        $document = $this->createDocument($employee);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/documents/{$document->id}");

        $response->assertForbidden();
    }

    public function test_user_can_delete_document(): void
    {
        $employee = $this->createEmployee();

        $file = UploadedFile::fake()->create(
            'ktp.pdf',
            100,
            'application/pdf',
        );

        $document = app(DocumentService::class)->upload(
            employeeId: $employee->id,
            uploadedBy: $this->user->id,
            file: $file,
            name: 'KTP',
        );

        $filePath = $document->file_path;

        Storage::disk('public')->assertExists($filePath);

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/api/v1/documents/{$document->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('public')->assertMissing($filePath);
    }
}
