<?php

namespace App\Http\Requests\Career;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCareerPathPositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'career_path_id' => [
                'sometimes',
                'integer',
                'exists:career_paths,id',
            ],
            'position_id' => [
                'sometimes',
                'integer',
                'exists:positions,id',
            ],
            'sequence' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}
