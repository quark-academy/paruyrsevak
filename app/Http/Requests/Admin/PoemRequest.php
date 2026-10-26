<?php

namespace App\Http\Requests\Admin;

use App\Support\PoemThemes;
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
            'themes' => ['nullable', 'array'],
            'themes.*' => ['string', Rule::in(array_keys(PoemThemes::LABELS))],
            'is_original' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $themes = $this->input('themes');
        $themes = is_array($themes) ? $themes : [];

        $this->merge([
            'themes' => PoemThemes::normalize($themes),
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
            'themes' => 'թեմաներ',
            'meta_title' => 'SEO վերնագիր',
            'meta_description' => 'SEO նկարագրություն',
        ];
    }
}
