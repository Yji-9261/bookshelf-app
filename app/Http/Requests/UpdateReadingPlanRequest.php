<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date_format:Y-m-d']
        ];
    }

    public function messages()
    {
        return [
            'target_date.required' => '期日を入力してください',
            'target_date.date_format' => '出版日をyyyy-mm-dd形式で入力してください',
        ];
    }
}
