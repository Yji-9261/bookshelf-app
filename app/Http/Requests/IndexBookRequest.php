<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'genre' => ['nullable', 'sometimes', 'integer', 'exists:genres,id'],
            'sort' => ['nullable', 'sometimes', 'string',
                Rule::in([
                    'newest',
                    'oldest',
                    'rating',
                    'title',
                ]),
            ],
        ];
    }
}
