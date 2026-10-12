<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
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
            'keyword' => ['sometimes', 'string', 'max:255'],
            'genre' => ['sometimes', 'string', 'exists:genres,name'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力して下さい',
            'keyword.max' => 'キーワードを255文字以内で入力してください',

            'genre.exists' => 'ジャンルが存在しません',

            'page.integer' => '数値を入力してください',
            'page.min' => '１以上の数値を入力してください',

            'per_page.integer' => '数値を入力してください',
            'per_page.min' => '１以上の数値を入力してください',
            'per_page.max' => '１００以下の数値を入力してください',
        ];
    }
}
