<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PoemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $poem = $this->route('poem');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[\p{L}\p{N}\-]+$/u',
                Rule::unique('poems', 'slug')->ignore($poem?->getKey()),
            ],
            'content' => ['required', 'string'],
            'is_original' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_original' => $this->boolean('is_original'),
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'վերնագիր',
            'slug' => 'URL (slug)',
            'content' => 'տեքստ',
            'meta_title' => 'SEO վերնագիր',
            'meta_description' => 'SEO նկարագրություն',
        ];
    }
}
