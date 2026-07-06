<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ReportSource;

use App\Models\ReportSource;
use App\MoonShine\Resources\ReportSource\Pages\ReportSourceFormPage;
use App\MoonShine\Resources\ReportSource\Pages\ReportSourceIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<ReportSource, ReportSourceIndexPage, ReportSourceFormPage>
 */
class ReportSourceResource extends ModelResource
{
    protected string $model      = ReportSource::class;
    protected string $column     = 'service_name';
    protected string $sortColumn = 'id';
    protected int    $itemsPerPage = 20;

    public function getTitle(): string
    {
        return 'Report Sources';
    }

    public function getItemsPerPage(): int
    {
        $value = (int) (session()?->get('reportSourcePerPage') ?? $this->itemsPerPage);
        return in_array($value, $this->perPageValues()) ? $value : $this->itemsPerPage;
    }

    public function perPageValues(): array
    {
        return [10 => 10, 20 => 20, 50 => 50];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ReportSourceIndexPage::class,
            ReportSourceFormPage::class,
        ];
    }
}
