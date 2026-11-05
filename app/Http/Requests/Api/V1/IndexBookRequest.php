<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array {
        return [
            'keyword' => ['nullable', 'sometimes', 'string', 'max:255'],
            'genre' => ['nullable', 'sometimes', 'string', 'exists:genres,name'],
            'page' => ['nullable', 'sometimes', 'integer', 'min:1'],
            'per_page' => ['nullable', 'sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * バリデーションメッセージ
     */
    public function messages(): array {
        return [
            'keyword.string' => 'キーワードは文字列で入力して下さい',
            'keyword.max' => 'キーワードを255文字以内で入力してください',

            'genre.string' => 'ジャンルは文字列で入力して下さい',
            'genre.exists' => 'ジャンルが存在しません',

            'page.integer' => '数値を入力してください',
            'page.min' => '1以上の数値を入力してください',

            'per_page.integer' => '数値を入力してください',
            'per_page.min' => '1以上の数値を入力してください',
            'per_page.max' => '100以下の数値を入力してください',
        ];
    }
}
