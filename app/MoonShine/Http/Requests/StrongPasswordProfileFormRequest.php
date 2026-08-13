<?php

declare(strict_types=1);

namespace App\MoonShine\Http\Requests;

use Illuminate\Validation\Rules\Password;
use MoonShine\Laravel\Http\Requests\ProfileFormRequest;
use MoonShine\Laravel\MoonShineAuth;

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
 *
 * Field username (email login) juga dikunci di sini: App\MoonShine\Pages\ProfilePage sudah
 * menandai field-nya ->readonly(), tapi itu cuma atribut HTML - request mentah tetap bisa
 * dimanipulasi untuk mengirim nilai lain. Validasi di sini menolak perubahan apa pun pada field
 * itu, terlepas dari apa yang dikirim client.
 */
class StrongPasswordProfileFormRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $passwordField = moonshineConfig()->getUserField('password');

        if (! blank($passwordField) && isset($rules[$passwordField])) {
            $rules[$passwordField] = [
                'sometimes',
                'nullable',
                Password::defaults(),
                'required_with:password_repeat',
                'same:password_repeat',
            ];
        }

        $usernameField = moonshineConfig()->getUserField('username');

        if (! blank($usernameField) && isset($rules[$usernameField])) {
            $currentValue = MoonShineAuth::getGuard()->user()?->{$usernameField};

            $rules[$usernameField][] = function (string $attribute, mixed $value, \Closure $fail) use ($currentValue): void {
                if ($currentValue !== null && $value !== $currentValue) {
                    $fail('Email tidak dapat diubah lewat halaman profil.');
                }
            };
        }

        return $rules;
    }
}
