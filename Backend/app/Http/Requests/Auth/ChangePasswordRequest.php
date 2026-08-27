<?php

namespace App\Http\Requests\Auth;


use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => [
                'required',
                'current_password',
            ],

            'new_password' => [
                'required',
                'confirmed',
                'min:8',
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'current_password.current_password' =>  'Password saat ini tidak sesuai.',
            'new_password.confirmed' =>  'Konfirmasi password baru tidak sesuai.',
            'new_password.min' =>  'Password baru minimal 8 karakter.',
        ];
    }
}
