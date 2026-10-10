<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateBookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'digits:13',
                // 新規登録・更新共通のカスタムリクエスト
                // 新規登録時のroute('book')はnullのためignore部分は処理されない
                Rule::unique('books', 'isbn')->ignore($this->route('book'))
            ],
            'published_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string'],
            'genres' => ['required', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'image_url' => ['nullable', 'url', 'max:2048']
        ];
    }

    /**
     * バリデーションエラー時メッセージ
     * 
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.required' => '書籍のタイトルを入力してください',
            'title.max' => '書籍のタイトルを255文字以内で入力してください',

            'author.required' => '著者名を入力してください',
            'author.max' => '著者名を255文字以内で入力してください',

            'isbn.required' => 'ISBNを入力してください',
            'isbn.digits' => 'ISBNを13桁の数字で入力してください（ハイフン不要）',
            'isbn.unique' => 'このISBNは登録済みです',

            'published_date.required' => '出版日を入力してください',
            'published_date.date_format' => '出版日をYYYY年MM月DD日形式で入力してください',

            'genres.required' => 'ジャンルを選択してください',

            'genres.*.exists' => '登録済みのジャンルを入力して下さい',

            'image_url.url' => 'URL形式で入力してください',
            'image_url.max' => 'URLは2048文字以内で入力してください',
        ];
    }
}
