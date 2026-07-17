<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonShineUserRole;

use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Resources\ModelResource;
use App\MoonShine\Resources\MoonShineUserRole\Pages\MoonShineUserRoleFormPage;
use App\MoonShine\Resources\MoonShineUserRole\Pages\MoonShineUserRoleIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<MoonshineUserRole, MoonShineUserRoleIndexPage, MoonShineUserRoleFormPage, null>
 */
#[Icon('bookmark')]
#[Group('moonshine::ui.resource.system', 'users', translatable: true)]
#[Order(1)]
class MoonShineUserRoleResource extends ModelResource
{
    protected string $model = MoonshineUserRole::class;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $detailInModal = true;

    protected bool $editInModal = true;

    protected bool $cursorPaginate = true;

    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getTitle(): string
    {
        return __('moonshine::ui.resource.role');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    protected function pages(): array
    {
        return [
            MoonShineUserRoleIndexPage::class,
            MoonShineUserRoleFormPage::class,
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
     * @param DataWrapperContract<MoonshineUserRole> $item
     * @return DataWrapperContract<MoonshineUserRole>
     */
    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        if ((int) $item->getKey() === MoonshineUserRole::DEFAULT_ROLE_ID) {
            throw new ResourceException('Role Admin tidak dapat dihapus.');
        }

        $userCount = $this->countUsersWithRole([(int) $item->getKey()]);

        if ($userCount > 0) {
            throw new ResourceException(
                "Role ini masih dipakai oleh {$userCount} user aktif. Pindahkan atau hapus user tersebut terlebih dahulu sebelum menghapus role ini."
            );
        }

        return $item;
    }

    /**
     * @param list<int> $ids
     */
    protected function beforeMassDeleting(array $ids): void
    {
        $ids = array_map('intval', $ids);

        if (in_array(MoonshineUserRole::DEFAULT_ROLE_ID, $ids, true)) {
            throw new ResourceException('Role Admin tidak dapat dihapus.');
        }

        $userCount = $this->countUsersWithRole($ids);

        if ($userCount > 0) {
            throw new ResourceException(
                "Salah satu atau lebih role yang dipilih masih dipakai oleh total {$userCount} user aktif. Pindahkan atau hapus user tersebut terlebih dahulu."
            );
        }
    }

    /**
     * @param list<int> $roleIds
     */
    private function countUsersWithRole(array $roleIds): int
    {
        return MoonshineUser::whereIn('moonshine_user_role_id', $roleIds)->count();
    }
}
