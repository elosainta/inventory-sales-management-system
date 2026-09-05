<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['required', 'string', 'max:2000'],
            // Supporting documents are mandatory. max: is in KB — 51200 KB = 50 MB.
            // The array needs its own max: too; without one the per-file cap is
            // meaningless because the element count is unbounded, and the real
            // ceiling becomes nginx's client_max_body_size against a 13 GB disk.
            // 50 MB × 5 is generous against real use — the largest file ever
            // uploaded to this system is a 29.8 MB support screen recording.
            'attachments'   => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,pdf', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.required' => 'At least one supporting document is required.',
            'attachments.max'      => 'You can attach up to 5 files.',
            'attachments.*.mimes'  => 'Only images, videos (mp4/mov), and PDF files are allowed.',
            'attachments.*.max'    => 'Each file must be 50 MB or smaller.',
        ];
    }
}
