<?php

namespace App\Http\Controllers\Api\V1\SystemSupport;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemSupport\StoreDocumentRequest;
use App\Http\Resources\V1\SystemSupport\DocumentResource;
use App\Services\SystemSupport\DocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:document.view', only: ['index', 'show', 'download']),
            new Middleware('permission:document.create', only: ['store']),
            new Middleware('permission:document.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $employeeId = $request->integer('employee_id');

        $documents = $this->documentService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            employeeId: $employeeId > 0 ? $employeeId : null,
        );

        return ApiResponse::success(
            data: DocumentResource::collection($documents),
            message: 'Daftar document berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'from' => $documents->firstItem(),
                    'to' => $documents->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documentService->upload(
            employeeId: $request->integer('employee_id'),
            uploadedBy: Auth::id(),
            file: $request->file('file'),
            name: $request->string('name')->trim()->toString(),
            description: $request->input('description'),
        );

        return ApiResponse::success(
            data: DocumentResource::make($document),
            message: 'Document berhasil diupload.',
        );
    }

    public function show(int $document): JsonResponse
    {
        $document = $this->documentService->findById($document);

        return ApiResponse::success(
            data: DocumentResource::make($document),
            message: 'Detail document berhasil diambil.',
        );
    }

    public function download(int $document): BinaryFileResponse
    {
        $document = $this->documentService->findById($document);

        return response()->download(
            $this->documentService->getDownloadPath($document),
            $document->file_name,
            [
                'Content-Type' => $document->mime_type,
            ],
        );
    }

    public function destroy(int $document): JsonResponse
    {
        $document = $this->documentService->findById($document);

        $this->documentService->delete($document);

        return ApiResponse::success(
            message: 'Document berhasil dihapus.',
        );
    }
}
