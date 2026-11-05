<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateBookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'digits:13',
                // 新規登録・更新共通のカスタムリクエスト
                // 新規登録時のroute('book')はnullのためignore部分は処理されない
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
            'published_date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:10000'],
            'genres' => ['required', 'array'],
            'genres.*' => ['string', 'exists:genres,name'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * バリデーションメッセージ
     */
    public function messages(): array {
        return [
            'title.required' => '書籍のタイトルを入力してください',
            'title.string' => '書籍のタイトルは文字列で入力してください',
            'title.max' => '書籍のタイトルを255文字以内で入力してください',

            'author.required' => '著者名を入力してください',
            'author.string' => '著者名は文字列で入力してください',
            'author.max' => '著者名を255文字以内で入力してください',

            'isbn.required' => 'ISBNを入力してください',
            'isbn.digits' => 'ISBNを13桁の数字で入力してください（ハイフン不要）',
            'isbn.unique' => 'このISBNは登録済みです',

            'published_date.date_format' => '出版日をyyyy-mm-dd形式で入力してください',

            'description.max' => '説明は10000文字以内で入力してください',

            'genres.required' => 'genresを入力してください',
            'genres.array' => 'genresは配列形式で入力してください',

            'genres.*.string' => 'ジャンルは文字列で入力してください',
            'genres.*.exists' => '登録済みのジャンルを入力して下さい',

            'image_url.url' => 'URL形式で入力してください',
            'image_url.max' => 'URLは2048文字以内で入力してください',
        ];
    }
}
