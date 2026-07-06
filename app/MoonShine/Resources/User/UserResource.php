<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User;

use App\Models\User;
use App\MoonShine\Resources\User\Pages\UserDetailPage;
use App\MoonShine\Resources\User\Pages\UserFormPage;
use App\MoonShine\Resources\User\Pages\UserIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<User, UserIndexPage, UserFormPage, UserDetailPage>
 */
class UserResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = User::class;
    protected string $column = 'email';
    protected int $itemsPerPage = 10;
    protected bool $usePagination = true;

    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('perPage') ?? $default);

        if (! in_array($value, $this->perPageValues())) {
            return $default;
        }

        return $value;
    }

    public function perPageValues(): array
    {
        return [
            5  => 5,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100,
        ];
    }

    protected function search(): array
    {
        return ['Name', 'E-mail'];
    }

    public function getTitle(): string
    {
        return __('Clients');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            UserIndexPage::class,
            UserFormPage::class,
            UserDetailPage::class,
        ];
    }

    // ✅ Fields yang akan diexport
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name'),
            Email::make('E-mail', 'email'),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('users_' . date('Ymd-His')),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('users_' . date('Ymd-His')),
        ]);
    }
}