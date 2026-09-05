<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Only the rules the sign-in form can actually produce. Every other rule
    | falls through to the framework's English set via the fallback locale, so
    | this file stays small instead of being a translated copy of a file nobody
    | reads. Add a rule here when a form a chef uses starts producing it.
    |
    */

    'required' => ':attribute wajib diisi.',
    'email'    => ':attribute harus berupa alamat email yang valid.',
    'string'   => ':attribute harus berupa teks.',

    'attributes' => [
        'email'    => 'Email',
        'password' => 'Kata sandi',
    ],

];
