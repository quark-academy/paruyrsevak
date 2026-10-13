<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveTimingsRequest extends FormRequest
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
        return [
            'words' => ['required', 'array'],
            'words.*.id' => ['nullable', 'integer'],
            'words.*.word' => ['required', 'string', 'max:255'],
            'words.*.start_time' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'words.*.end_time' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'words.*.word_order' => ['nullable', 'integer', 'min:0'],
            'words.*.delete' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'words.*.word' => 'բառ',
            'words.*.start_time' => 'սկիզբ (start_time)',
            'words.*.end_time' => 'ավարտ (end_time)',
        ];
    }
}
