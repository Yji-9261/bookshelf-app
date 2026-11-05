<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateGenreRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                // 新規登録・更新共通のカスタムリクエスト
                // 新規登録時のroute('genre')はnullのためignore部分は処理されない
                Rule::unique('genres', 'name')->ignore($this->route('genre')),
                'max:255',
            ],
        ];
    }

    /**
     * バリデーションメッセージ
     */
    public function messages(): array {
        return [
            'name.required' => 'ジャンルを入力してください',
            'name.unique' => 'このジャンルはすでに登録されています',
            'name.string' => 'ジャンルは文字列で入力してください',
            'name.max' => 'ジャンルを255文字以内で入力してください',
        ];
    }
}
