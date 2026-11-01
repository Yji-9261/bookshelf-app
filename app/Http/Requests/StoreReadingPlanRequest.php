<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans')->where('user_id', $this->user()->id)
            ],
            'target_date' => ['required', 'date_format:Y-m-d']
        ];
    }

    public function messages() {
        return [
            'book_id.required' => '書籍を入力してください',
            'book_id.integer' => '数値を入力してください',
            'book_id.exsits' => '書籍が存在しません',
            'book_id.unique' => 'この書籍は既に進行中の読書計画が存在します',

            'target_date.required' => '期日を入力してください',
            'target_date.date_format' => '出版日をyyyy-mm-dd形式で入力してください',
        ];
    }
}
