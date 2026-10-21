<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Enums\Sort;

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
            'keyword' => ['nullable', 'sometimes', 'string', 'max:255'],
            'genre' => ['nullable', 'sometimes', 'integer'],
            'sort' => ['nullable', 'sometimes', 'string'],
        ];
    }

    // public function messages()
    // {
    //     return [
    //         'keyword.string' => 'キーワードは文字列で入力して下さい',
    //         'keyword.max' => 'キーワードを255文字以内で入力してください',

    //         'genre.exists' => 'ジャンルが存在しません',

    //         'sort.in' => '並び順を選択してください',
    //     ];
    // }
}
