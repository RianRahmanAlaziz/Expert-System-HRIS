<?php

namespace App\Services\SystemSupport;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function upload(
        int $employeeId,
        int $uploadedBy,
        UploadedFile $file,
        string $name,
        ?string $description = null,
    ): Document {
        $path = $file->store(
            'documents',
            'public',
        );

        return Document::create([
            'employee_id' => $employeeId,
            'uploaded_by' => $uploadedBy,
            'name' => $name,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $description,
        ]);
    }

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $employeeId = null,
    ): LengthAwarePaginator {
        $query = Document::query()
            ->with(['employee', 'uploader'])
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like',  "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                $employeeId,
                fn($query) => $query->where(
                    'employee_id',
                    $employeeId,
                ),
            )->latest();

        return $query->paginate($perPage);
    }

    public function findById(int $id): Document
    {
        return Document::query()
            ->with(['employee', 'uploader'])
            ->findOrFail($id);
    }

    public function getDownloadPath(Document $document): string
    {
        return Storage::disk('public')->path($document->file_path);
    }

    public function delete(Document $document): void
    {
        Storage::disk('public')->delete($document->file_path);

        $document->delete();
    }
}
