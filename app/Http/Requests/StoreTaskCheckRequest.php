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
            'photo'   => [$photoRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
