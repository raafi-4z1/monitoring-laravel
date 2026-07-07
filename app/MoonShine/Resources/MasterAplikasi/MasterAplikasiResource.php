<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MasterAplikasi;

use App\Models\MasterAplikasi;
use App\MoonShine\Resources\MasterAplikasi\Pages\MasterAplikasiFormPage;
use App\MoonShine\Resources\MasterAplikasi\Pages\MasterAplikasiIndexPage;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\PageType;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<MasterAplikasi, MasterAplikasiIndexPage, MasterAplikasiFormPage>
 */
class MasterAplikasiResource extends ModelResource
{
    protected string $model  = MasterAplikasi::class;
    protected string $column = 'nama';
    protected int $itemsPerPage = 20;
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getTitle(): string
    {
        return 'Master Aplikasi';
    }

    // Sertakan soft-deleted agar QueryTag 'Sampah' bisa bekerja
    protected function resolveQuery(): Builder
    {
        return MasterAplikasi::withTrashed();
    }

    public function getItemsPerPage(): int
    {
        $value = (int) (session()?->get('masterAplikasiPerPage') ?? $this->itemsPerPage);
        return in_array($value, $this->perPageValues()) ? $value : $this->itemsPerPage;
    }

    public function perPageValues(): array
    {
        return [10 => 10, 20 => 20, 50 => 50];
    }

    protected function search(): array
    {
        return [];
    }

    protected function pages(): array
    {
        return [
            MasterAplikasiIndexPage::class,
            MasterAplikasiFormPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [ID::make(), Text::make('Nama Aplikasi', 'nama'), Text::make('Keterangan', 'keterangan')];
    }
}
