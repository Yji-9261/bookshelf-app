<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * バリデーションメッセージ
     */
    public function messages(): array {
        return [
            'rating.required' => '評価を入力してください',
            'rating.integer' => '評価は数値を入力してください',
            'rating.min' => '評価は１以上の数値を入力してください',
            'rating.max' => '評価は５以下の数値を入力してください',

            'comment.required' => 'コメントを入力してください',
            'comment.string' => 'コメントは文字列で入力してください',
            'comment.max' => 'コメントは10000文字以内で入力してください',
        ];
    }
}
