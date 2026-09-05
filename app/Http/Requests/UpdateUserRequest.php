<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'role'       => ['required', Rule::in([
                User::ROLE_OWNER,
                User::ROLE_HEAD_CHEF,
                User::ROLE_JUNIOR_CHEF,
                User::ROLE_PART_TIMER,
            ])],
            'section_id' => ['nullable', 'exists:sections,id'],
        ];
    }
}
