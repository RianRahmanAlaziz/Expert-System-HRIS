<?php

namespace App\Http\Controllers\Api\V1\Consultation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\StoreExpertConsultationRequest;
use App\Http\Resources\V1\Consultation\ExpertConsultationResource;
use App\Models\ExpertConsultation;
use App\Services\Consultation\ExpertConsultationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpertConsultationController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ExpertConsultationService $expertConsultationService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:expert_consultation.view', only: ['index', 'show']),
            new Middleware('permission:expert_consultation.create', only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $search = trim((string) $request->query('search', ''));

        $employeeId = $request->integer('employee_id');

        $consultationType = trim((string) $request->query('consultation_type', ''));

        $consultations = $this->expertConsultationService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            employeeId: $employeeId > 0 ? $employeeId : null,
            consultationType: $consultationType !== ''
                ? $consultationType
                : null,
        );

        return ApiResponse::success(
            data: ExpertConsultationResource::collection(
                $consultations,
            ),
            message: 'Daftar expert consultation berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $consultations->currentPage(),
                    'last_page' => $consultations->lastPage(),
                    'per_page' => $consultations->perPage(),
                    'total' => $consultations->total(),
                    'from' => $consultations->firstItem(),
                    'to' => $consultations->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreExpertConsultationRequest $request,
    ): JsonResponse {
        $consultation = $this->expertConsultationService->create(
            user: $request->user(),
            data: $request->validated(),
        );

        return ApiResponse::success(
            data: ExpertConsultationResource::make($consultation),
            message: 'Expert consultation berhasil dibuat.',
            status: 201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(
        ExpertConsultation $expertConsultation,
    ): JsonResponse {
        $consultation = $this->expertConsultationService->findById(
            $expertConsultation->id,
        );

        return ApiResponse::success(
            data: ExpertConsultationResource::make($consultation),
            message: 'Detail expert consultation berhasil diambil.',
        );
    }
}
