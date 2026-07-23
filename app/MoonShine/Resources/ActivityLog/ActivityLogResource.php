<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ActivityLog;

use App\Models\ActivityLog;
use App\MoonShine\Handlers\GuardedExportHandler as ExportHandler;
use App\MoonShine\Resources\ActivityLog\Pages\ActivityLogIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * Read-only audit trail — tidak ada create/update/delete lewat UI, data cuma masuk lewat
 * ActivityLogger (observer model, event login/logout, dan log manual di titik-titik penting).
 *
 * @extends ModelResource<ActivityLog, ActivityLogIndexPage, ActivityLogIndexPage>
 */
class ActivityLogResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model       = ActivityLog::class;
    protected string $column      = 'created_at';
    protected string $title       = 'Activity Log';
    protected string $sortColumn  = 'created_at';
    protected int $itemsPerPage   = 50;
    protected bool $usePagination = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::VIEW, Action::CREATE, Action::UPDATE, Action::DELETE, Action::MASS_DELETE);
    }

    public function getItemsPerPage(): int
    {
        $default = $this->itemsPerPage;
        $value   = (int) (session()?->get('activityLogPerPage') ?? $default);

        return in_array($value, $this->perPageValues()) ? $value : $default;
    }

    public function perPageValues(): array
    {
        return [25 => 25, 50 => 50, 100 => 100, 200 => 200];
    }

    protected function search(): array
    {
        return [];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ActivityLogIndexPage::class,
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            Preview::make('waktu', 'created_at')->changeFill(fn($item) => $item->created_at?->format('Y-m-d H:i:s') ?? ''),
            Text::make('user', 'user_name'),
            Text::make('email', 'user_email'),
            Text::make('ip_address', 'ip_address'),
            Text::make('aksi', 'action'),
            Text::make('deskripsi', 'description'),
            Preview::make('detail', 'properties')->changeFill(fn($item) => $item->properties ? json_encode($item->properties) : ''),
        ];
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            ExportHandler::make('Export Excel')->alias('export-excel')->filename('activity_log_' . date('Ymd-His'))->forceSort('created_at'),
            ExportHandler::make('Export CSV')->alias('export-csv')->csv()->filename('activity_log_' . date('Ymd-His'))->forceSort('created_at'),
        ]);
    }
}
