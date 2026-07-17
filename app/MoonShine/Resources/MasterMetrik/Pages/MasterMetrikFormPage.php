<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MasterMetrik\Pages;

use App\Enums\MetricUnit;
use App\Models\MasterMetrik;
use App\MoonShine\Resources\MasterMetrik\MasterMetrikResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

/**
 * @extends FormPage<MasterMetrikResource>
 */
class MasterMetrikFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make('Data Metric', [
                        ID::make(),
                        Text::make('Nama Metrik', 'nama')
                            ->required()
                            ->placeholder('mis. CPU, MEMORY, DISK')
                            ->hint('Otomatis diubah ke UPPERCASE.'),
                        Select::make('Satuan Default', 'satuan_default')
                            ->options(MetricUnit::options())
                            ->nullable()
                            ->hint('Satuan yang biasanya digunakan untuk metrik ini.'),
                        Textarea::make('Keterangan', 'keterangan')
                            ->placeholder('Deskripsi singkat metrik (opsional)')
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
                Rule::unique('master_metrik', 'nama')
                    ->whereNull('deleted_at')
                    ->ignore($item->id),
            ],
            'satuan_default' => ['nullable', Rule::in(array_merge([''], MetricUnit::values()))],
            'keterangan'     => 'nullable|string|max:255',
        ];
    }

    protected function topLayer(): array { return [...parent::topLayer()]; }
    protected function mainLayer(): array { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
