<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonShineUser\Pages;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Pages\Crud\FormPage;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<MoonShineUserResource, MoonShineUser>
 */
final class MoonShineUserFormPage extends FormPage
{
    private const EMAIL_LOCKED_MESSAGE = 'Email tidak dapat diubah setelah akun dibuat.';

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('moonshine::ui.resource.main_information'), [
                        ID::make(),

                        BelongsTo::make(
                            __('moonshine::ui.resource.role'),
                            'moonshineUserRole',
                            formatted: static fn (MoonshineUserRole $model) => $model->name,
                            resource: MoonShineUserRoleResource::class,
                        )
                            ->creatable()
                            ->valuesQuery(static fn (Builder $q) => $q->select(['id', 'name'])),

                        Flex::make([
                            Text::make(__('moonshine::ui.resource.name'), 'name')
                                ->required(),

                            Email::make(__('moonshine::ui.resource.email'), 'email')
                                ->required()
                                ->readonly($this->isItemExists()),
                        ]),

                        Image::make(__('moonshine::ui.resource.avatar'), 'avatar')
                            ->disk(moonshineConfig()->getDisk())
                            ->dir(moonshineConfig()->getUserAvatarsDir())
                            ->allowedExtensions(['jpg', 'png', 'jpeg', 'gif']),

                        Date::make(__('moonshine::ui.resource.created_at'), 'created_at')
                            ->format("d.m.Y")
                            ->default(now()->toDateTimeString()),
                    ])->icon('user-circle'),

                    Tab::make(__('moonshine::ui.resource.password'), [
                        Collapse::make(__('moonshine::ui.resource.change_password'), [
                            Password::make(__('moonshine::ui.resource.password'), 'password')
                                ->customAttributes(['autocomplete' => 'new-password'])
                                ->eye(),

                            PasswordRepeat::make(__('moonshine::ui.resource.repeat_password'), 'password_confirmation')
                                ->customAttributes(['autocomplete' => 'confirm-password'])
                                ->eye(),
                        ])->icon('lock-closed'),
                    ])->icon('lock-closed'),
                ]),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => 'required',
            'moonshine_user_role_id' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($item): void {
                    $authUser = auth(moonshineConfig()->getGuard())->user();

                    if (! $authUser instanceof MoonshineUser || $item->getKey() === null) {
                        return;
                    }

                    $isEditingSelf = (int) $item->getKey() === (int) $authUser->getKey();

                    if (
                        $isEditingSelf
                        && $authUser->isSuperUser()
                        && (int) $value !== MoonshineUserRole::DEFAULT_ROLE_ID
                    ) {
                        $fail('Anda tidak dapat menurunkan role Anda sendiri dari Admin.');

                        return;
                    }

                    if (! $isEditingSelf) {
                        $targetIsCurrentlyAdmin = MoonshineUser::where('id', $item->getKey())
                            ->where('moonshine_user_role_id', MoonshineUserRole::DEFAULT_ROLE_ID)
                            ->exists();

                        if ($targetIsCurrentlyAdmin) {
                            $fail('Anda tidak dapat mengubah role Admin lain.');
                        }
                    }
                },
            ],
            'email' => [
                'sometimes',
                'bail',
                'required',
                'email',
                Rule::unique($item->getOriginal()::class)->ignoreModel($item->getOriginal()),
                // Field ini ->readonly() di fields(), tapi itu cuma atribut HTML - request
                // mentah tetap bisa dimanipulasi. Kunci beneran ada di sini: tolak perubahan
                // apa pun begitu user sudah ada (email cuma bisa diisi sekali, saat dibuat).
                function (string $attribute, mixed $value, \Closure $fail) use ($item): void {
                    if ($item->getKey() !== null && $value !== $item->getOriginal()->email) {
                        $fail(self::EMAIL_LOCKED_MESSAGE);
                    }
                },
            ],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif'],
            'password' => [
                ...$item->getKey() !== null ? ['sometimes', 'nullable'] : ['required'],
                PasswordRule::defaults(),
                'confirmed',
            ],
        ];
    }

    /**
     * MoonShineFormRequest::messages() menggabungkan lang/en/validation.php milik MoonShine
     * (berisi key generik seperti 'email' => 'The :attribute must be a valid email address.')
     * ke pesan validasi. Laravel sendiri, di Validator::getFromLocalArray(), memakai NAMA FIELD
     * sebagai salah satu key fallback pencarian pesan custom - karena field ini kebetulan
     * bernama "email" (sama seperti nama rule "email"), pesan generik itu SELALU menang
     * dibanding $fail() manapun di closure pada field ini, apa pun isi pesannya.
     *
     * Field 'moonshine_user_role_id' tidak kena masalah ini karena namanya tidak bentrok
     * dengan nama rule bawaan apa pun.
     *
     * Solusinya: daftarkan pesan di sini dengan key "{field}.{class rule}" (format paling
     * spesifik yang dicek Laravel duluan) - lihat Illuminate\Validation\Validator::
     * validateUsingCustomRule() dan Concerns\FormatsMessages::getFromLocalArray().
     *
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        return [
            'email.' . \Illuminate\Validation\ClosureValidationRule::class => self::EMAIL_LOCKED_MESSAGE,
        ];
    }
}
