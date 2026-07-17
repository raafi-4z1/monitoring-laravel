<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonShineUser;

use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Resources\ModelResource;
use App\MoonShine\Resources\MoonShineUser\Pages\MoonShineUserFormPage;
use App\MoonShine\Resources\MoonShineUser\Pages\MoonShineUserIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<MoonshineUser, MoonShineUserIndexPage, MoonShineUserFormPage, null>
 */
#[Icon('chart-bar')]
#[Group('moonshine::ui.resource.system', 'users', translatable: true)]
#[Order(0)]
class MoonShineUserResource extends ModelResource
{
    protected string $model = MoonshineUser::class;

    protected string $column = 'name';

    protected array $with = ['moonshineUserRole'];

    protected bool $simplePaginate = true;
    
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getTitle(): string
    {
        return __('moonshine::ui.resource.admins_title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    protected function pages(): array
    {
        return [
            MoonShineUserIndexPage::class,
            MoonShineUserFormPage::class,
        ];
    }

    protected function search(): array
    {
        return [
            'id',
            'name',
        ];
    }

    /**
     * @param DataWrapperContract<MoonshineUser> $item
     * @return DataWrapperContract<MoonshineUser>
     */
    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        $this->guardAgainstDeletingUsers([(int) $item->getKey()]);

        return $item;
    }

    /**
     * @param list<int> $ids
     */
    protected function beforeMassDeleting(array $ids): void
    {
        $this->guardAgainstDeletingUsers(array_map('intval', $ids));
    }

    /**
     * @param list<int> $ids
     */
    private function guardAgainstDeletingUsers(array $ids): void
    {
        $authUser = auth(moonshineConfig()->getGuard())->user();

        if ($authUser instanceof MoonshineUser && in_array((int) $authUser->getKey(), $ids, true)) {
            throw new ResourceException('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $otherAdminCount = MoonshineUser::where('moonshine_user_role_id', MoonshineUserRole::DEFAULT_ROLE_ID)
            ->whereIn('id', $ids)
            ->count();

        if ($otherAdminCount > 0) {
            throw new ResourceException('Anda tidak dapat menghapus akun Admin lain.');
        }

        // Safety net tambahan: mencegah semua Admin terhapus dalam satu aksi
        $superUserCount = MoonshineUser::where('moonshine_user_role_id', MoonshineUserRole::DEFAULT_ROLE_ID)->count();
        $deletedSuperUserCount = MoonshineUser::where('moonshine_user_role_id', MoonshineUserRole::DEFAULT_ROLE_ID)
            ->whereIn('id', $ids)
            ->count();

        if ($deletedSuperUserCount > 0 && $deletedSuperUserCount >= $superUserCount) {
            throw new ResourceException('Tidak dapat menghapus Admin terakhir yang tersisa.');
        }
    }
}
