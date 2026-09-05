<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:3000'],
            // max: is in KB — 131072 KB = 128 MB. Support media is usually a
            // screen recording; the largest ever uploaded is 29.8 MB, so this
            // leaves 4x headroom while bounding what one report can cost the
            // disk. Was 1 GB, which the droplet does not have room to honour.
            'media'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,pdf', 'max:131072'],
        ];
    }
}
