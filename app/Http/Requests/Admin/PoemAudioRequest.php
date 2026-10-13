<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PoemAudioRequest extends FormRequest
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
            'audio' => [
                'required',
                'file',
                'max:102400',
                'mimetypes:audio/mpeg,audio/mp3,audio/x-mpeg,audio/mpeg3,audio/x-mpeg-3,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,application/octet-stream',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.mimetypes' => 'Ֆայլը պետք է աուդիո լինի (.mp3, .wav, .m4a)։',
            'audio.max' => 'Աուդիոն չպետք է գերազանցի 100 ՄԲ։',
        ];
    }
}
