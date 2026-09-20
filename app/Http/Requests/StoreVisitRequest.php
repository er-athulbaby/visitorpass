<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = Setting::current()->deployment_mode;

        $rules = [
            'cpr_number' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
        ];

        if ($mode === 'company') {
            $rules['employee_id'] = ['required', 'exists:employees,id'];
            $rules['company_id'] = ['prohibited'];
        } else {
            $rules['company_id'] = ['required', 'exists:companies,id'];
            $rules['employee_id'] = ['prohibited'];
        }

        return $rules;
    }

    public function departmentIdForEmployee(): ?int
    {
        if (! $this->filled('employee_id')) {
            return null;
        }

        return Employee::find($this->input('employee_id'))?->department_id;
    }
}
