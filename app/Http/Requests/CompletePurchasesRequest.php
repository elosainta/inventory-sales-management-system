<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * The Upload button on a supplier's dropdown in the Purchase log: one photo
 * completes every pending purchase it was shown beside.
 */
class CompletePurchasesRequest extends FormRequest
{
    /**
     * Carried here as well as in the controller: a Form Request validates
     * before the controller runs, so without it an unauthorised malformed
     * post would get a 302 instead of a 403.
     */
    public function authorize(): bool
    {
        return Gate::allows('manage-purchases');
    }

    public function rules(): array
    {
        return [
            'purchase_ids'   => ['required', 'array'],
            'purchase_ids.*' => ['integer'],
            // 25 MB, as on the prep checklist: at 5 MB a phone photo was
            // routinely refused (2026-09-13).
            'photo'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
        ];
    }

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

    /**
     * As a toast, not a field error: this page shows no field errors, and the
     * button sits in a dropdown that is collapsed again after the reload, so
     * a refused photo would otherwise look like nothing happened.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(back()->with('error', $validator->errors()->first()));
    }
}
