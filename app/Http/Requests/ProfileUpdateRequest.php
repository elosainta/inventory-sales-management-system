<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * A Form Request validates BEFORE the controller runs, so the gate in
     * ProfileController::update fires only for a payload that already passed
     * validation — an unauthorised user submitting a malformed one gets a 302
     * back rather than a 403. Authorising here puts the check first, where it
     * belongs. The controller keeps its Gate::authorize as well.
     */
    public function authorize(): bool
    {
        return Gate::allows('edit-profile');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'preferred_language' => ['nullable', 'in:id'],
        ];
    }
}
