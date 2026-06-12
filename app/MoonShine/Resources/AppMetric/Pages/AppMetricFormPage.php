<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AppMetric\Pages;

use App\Enums\MetricUnit;
use App\Models\MasterAplikasi;
use App\Models\MasterMetrik;
use App\MoonShine\Resources\AppMetric\AppMetricResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;


/**
 * @extends FormPage<AppMetricResource>
 */
class AppMetricFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make('Data Metric', [
                        Date::make('Timestamp', 'recorded_at')
                            ->withTime()
                            ->inputFormat("Y-m-d\TH:i:s")
                            ->format('d M Y H:i:s')
                            ->customAttributes(['step' => '1'])
                            ->default(now()->format('Y-m-d\TH:i:s'))
                            ->required()
                            ->hint('Milidetik ditambahkan otomatis saat menyimpan.'),

                        Select::make('Nama Aplikasi', 'master_aplikasi_id')
                            ->options(
                                MasterAplikasi::pluck('nama', 'id')->toArray()
                            )
                            ->required()
                            ->searchable(),

                        Select::make('Metrik', 'master_metrik_id')
                            ->options(
                                MasterMetrik::pluck('nama', 'id')->toArray()
                            )
                            ->required()
                            ->searchable()
                            ->reactive(function (Fields $fields, mixed $value): Fields {
                                $satuanDefault = MasterMetrik::find($value)?->satuan_default ?? '';

                                $fields->each(function ($field) use ($satuanDefault): void {
                                    if (
                                        $field instanceof FieldContract
                                        && $field->getColumn() === 'satuan'
                                    ) {
                                        $field->setValue($satuanDefault);
                                    }
                                });

                                return $fields;
                            }),

                        Text::make('Value', 'value')
                            ->required()
                            ->placeholder('mis. 75.4, 2.1, 512'),

                        Select::make('Satuan', 'satuan')
                            ->options(MetricUnit::options())
                            ->required()
                            ->hint('Otomatis terisi saat metrik dipilih, bisa diubah manual.'),
                    ]),
                ])->columnSpan(6),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'recorded_at'        => 'required|date',
            'master_aplikasi_id' => 'required|integer|exists:master_aplikasi,id',
            'master_metrik_id'   => 'required|integer|exists:master_metrik,id',
            'value'              => 'required|string|max:255',
            'satuan'             => ['required', Rule::in(MetricUnit::values())],
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [...parent::topLayer()];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [...parent::mainLayer()];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [...parent::bottomLayer()];
    }
}
