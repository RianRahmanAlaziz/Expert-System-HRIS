<?php

namespace App\Http\Requests\Employee;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');
        $employeeId = is_object($employee)   ? $employee->id  : $employee;

        return [
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],

            'department_id' => [
                'sometimes',
                'integer',
                'exists:departments,id',
            ],

            'position_id' => [
                'sometimes',
                'integer',
                'exists:positions,id',
            ],

            'manager_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:employees,id',
                Rule::notIn([$employeeId]),
            ],

            'employee_number' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employeeId),
            ],

            'first_name' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'gender' => [
                'sometimes',
                'string',
                'max:20',
            ],

            'birth_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'join_date' => [
                'sometimes',
                'date',
            ],

            'employment_type' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'employment_status' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'history_reason' => [
                'nullable',
                'string',
                'max:255',
            ],

            'history_notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.integer' => 'User tidak valid.',
            'user_id.exists' => 'User tidak ditemukan.',
            'user_id.unique' => 'User sudah terhubung dengan employee lain.',

            'department_id.integer' => 'Department tidak valid.',
            'department_id.exists' => 'Department tidak ditemukan.',

            'position_id.integer' => 'Position tidak valid.',
            'position_id.exists' => 'Position tidak ditemukan.',

            'manager_id.integer' => 'Manager tidak valid.',
            'manager_id.exists' => 'Manager tidak ditemukan.',
            'manager_id.not_in' => 'Employee tidak dapat menjadi manager untuk dirinya sendiri.',

            'employee_number.string' => 'Nomor employee harus berupa teks.',
            'employee_number.max' => 'Nomor employee maksimal 50 karakter.',
            'employee_number.unique' => 'Nomor employee sudah digunakan.',

            'first_name.string' => 'Nama depan harus berupa teks.',
            'first_name.max' => 'Nama depan maksimal 100 karakter.',

            'last_name.string' => 'Nama belakang harus berupa teks.',
            'last_name.max' => 'Nama belakang maksimal 100 karakter.',

            'gender.string' => 'Jenis kelamin harus berupa teks.',
            'gender.max' => 'Jenis kelamin maksimal 20 karakter.',

            'birth_date.date' => 'Tanggal lahir harus berupa tanggal yang valid.',

            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',

            'address.string' => 'Alamat harus berupa teks.',

            'join_date.date' => 'Tanggal bergabung harus berupa tanggal yang valid.',

            'employment_type.string' => 'Tipe employment harus berupa teks.',
            'employment_type.max' => 'Tipe employment maksimal 50 karakter.',

            'employment_status.string' => 'Status employment harus berupa teks.',
            'employment_status.max' => 'Status employment maksimal 50 karakter.',

            'history_reason.string' => 'Alasan history harus berupa teks.',
            'history_reason.max' => 'Alasan history maksimal 255 karakter.',

            'history_notes.string' => 'Catatan history harus berupa teks.',
        ];
    }
}
