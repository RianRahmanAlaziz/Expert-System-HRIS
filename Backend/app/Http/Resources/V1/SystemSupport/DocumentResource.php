<?php

namespace App\Http\Resources\V1\SystemSupport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'uploaded_by' => $this->uploaded_by,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'description' => $this->description,
            'employee' => $this->whenLoaded(
                'employee',
                fn() => [
                    'id' => $this->employee->id,
                    'employee_number' => $this->employee->employee_number,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                ],
            ),
            'uploader' => $this->whenLoaded(
                'uploader',
                fn() => [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
