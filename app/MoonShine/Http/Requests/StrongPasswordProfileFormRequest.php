<?php

declare(strict_types=1);

namespace App\MoonShine\Http\Requests;

use Illuminate\Validation\Rules\Password;
use MoonShine\Laravel\Http\Requests\ProfileFormRequest;

/**
 * MoonShine\Laravel\Http\Requests\ProfileFormRequest (dipakai saat user ganti password sendiri
 * lewat halaman profil) memvalidasi password dengan aturan hardcode 'min:6' — TIDAK memakai
 * Password::defaults(). Akibatnya kebijakan password kuat yang diatur di
 * AppServiceProvider::configurePasswordPolicy() cuma berlaku saat admin membuat/mengubah user
 * lewat resource Users, tapi user bisa ganti passwordnya sendiri ke "123456" lewat profil
 * tanpa terhalang sama sekali.
 *
 * Diikat ke ProfileFormRequest lewat container binding di AppServiceProvider (bukan edit file
 * vendor), supaya kedua jalur ganti password memakai kebijakan yang sama.
 */
class StrongPasswordProfileFormRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $passwordField = moonshineConfig()->getUserField('password');

        if (blank($passwordField) || ! isset($rules[$passwordField])) {
            return $rules;
        }

        $rules[$passwordField] = [
            'sometimes',
            'nullable',
            Password::defaults(),
            'required_with:password_repeat',
            'same:password_repeat',
        ];

        return $rules;
    }
}
