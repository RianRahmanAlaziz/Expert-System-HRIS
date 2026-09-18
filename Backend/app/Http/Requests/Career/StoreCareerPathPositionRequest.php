<?php

namespace App\Http\Requests\Career;

use Illuminate\Foundation\Http\FormRequest;

class StoreCareerPathPositionRequest extends FormRequest
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
                'required',
                'integer',
                'exists:career_paths,id',
            ],
            'position_id' => [
                'required',
                'integer',
                'exists:positions,id',
            ],
            'sequence' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}
