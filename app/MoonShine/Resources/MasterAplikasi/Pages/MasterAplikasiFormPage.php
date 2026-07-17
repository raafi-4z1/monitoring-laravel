<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MasterAplikasi\Pages;

use App\Models\MasterAplikasi;
use App\MoonShine\Resources\MasterAplikasi\MasterAplikasiResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

/**
 * @extends FormPage<MasterAplikasiResource>
 */
class MasterAplikasiFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make('Data Aplikasi', [
                        ID::make(),
                        Text::make('Nama Aplikasi', 'nama')
                            ->required()
                            ->placeholder('mis. MTELEPLUS, ENGINE-NOTIF')
                            ->hint('Otomatis diubah ke UPPERCASE.'),
                        Textarea::make('Keterangan', 'keterangan')
                            ->placeholder('Deskripsi singkat aplikasi (opsional)')
                            ->nullable(),
                    ]),
                ])->columnSpan(6),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('master_aplikasi', 'nama')
                    ->whereNull('deleted_at')
                    ->ignore($item->id),
            ],
            'keterangan' => 'nullable|string|max:255',
        ];
    }

    protected function topLayer(): array { return [...parent::topLayer()]; }
    protected function mainLayer(): array { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
