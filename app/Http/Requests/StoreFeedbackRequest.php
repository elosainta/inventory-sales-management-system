<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'to_user_id'           => ['required', 'integer', 'exists:users,id', Rule::notIn([auth()->id()])],
            'rating_cleanliness'   => ['required', 'integer', 'between:1,5'],
            'rating_safety'        => ['required', 'integer', 'between:1,5'],
            'rating_organisation'  => ['required', 'integer', 'between:1,5'],
            'rating_teamwork'      => ['required', 'integer', 'between:1,5'],
            'rating_communication' => ['required', 'integer', 'between:1,5'],
            'comment'              => ['nullable', 'string', 'max:2000'],
            // max: is in KB — 51200 KB = 50 MB, same as leave attachments, and
            // the array carries its own max: for the same reason (see there).
            'attachments'          => ['nullable', 'array', 'max:5'],
            'attachments.*'        => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,pdf', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.not_in' => 'You cannot rate yourself.',
            '*.between'         => 'Each question needs a rating from 1 to 5 stars.',
        ];
    }
}
