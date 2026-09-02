<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('leave_types', 'code')->ignore($this->route('leaveType')),
            ],
            'default_days' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'sometimes',
                'string',
                'in:active,inactive',
            ],
        ];
    }
}
