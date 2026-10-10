<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string']
        ];
    }

    public function messages()
    {
        return [
            'rating.required' => '評価を入力してください。',
            'rating.integer' => '評価は数値を入力してください。',
            'rating.min' => '評価は１以上の数値を入力してください。',
            'rating.max' => '評価は５以下の数値を入力してください。',

            'comment' => 'コメントを入力してください。',
        ];
    }
}
