<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MasterMetrik;

use App\Models\MasterMetrik;
use App\MoonShine\Resources\MasterMetrik\Pages\MasterMetrikFormPage;
use App\MoonShine\Resources\MasterMetrik\Pages\MasterMetrikIndexPage;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\PageType;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<MasterMetrik, MasterMetrikIndexPage, MasterMetrikFormPage>
 */
class MasterMetrikResource extends ModelResource
{
    protected string $model  = MasterMetrik::class;
    protected string $column = 'nama';
    protected int $itemsPerPage = 20;
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getTitle(): string
    {
        return 'Master Metric';
    }

    // Sertakan soft-deleted agar QueryTag 'Sampah' bisa bekerja
    protected function resolveQuery(): Builder
    {
        return MasterMetrik::withTrashed();
    }

    public function getItemsPerPage(): int
    {
        $value = (int) (session()?->get('masterMetrikPerPage') ?? $this->itemsPerPage);
        return in_array($value, $this->perPageValues()) ? $value : $this->itemsPerPage;
    }

    public function perPageValues(): array
    {
        return [10 => 10, 20 => 20, 50 => 50];
    }

    protected function pages(): array
    {
        return [
            MasterMetrikIndexPage::class,
            MasterMetrikFormPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Nama Metrik', 'nama'),
            Text::make('Satuan Default', 'satuan_default'),
            Text::make('Keterangan', 'keterangan'),
        ];
    }
}
