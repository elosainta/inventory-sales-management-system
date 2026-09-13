<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCheckRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $task = \App\Models\SectionTask::find($this->input('task_id'));
        $photoRequired = !$task || $task->requires_photo;

        return [
            'task_id' => ['required', 'integer', 'exists:section_tasks,id'],
            // 25 MB, the same as sale photos. It was 5 MB, and a phone camera
            // photo is routinely bigger — every one was refused, and the page
            // showed nothing, so from the chef's side the upload just did not
            // work (2026-09-13).
            'photo'   => [$photoRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
        ];
    }

    /** Shown under the task on the checklist, to a chef with a phone in hand. */
    public function messages(): array
    {
        return [
            'photo.required' => __('Choose a photo first.'),
            'photo.image'    => __('That file is not a photo. Use JPG, PNG or WebP.'),
            'photo.mimes'    => __('That file is not a photo. Use JPG, PNG or WebP.'),
            'photo.max'      => __('That photo is too big. The limit is 25 MB.'),
            'photo.uploaded' => __('The photo did not upload. Try again.'),
        ];
    }
}
