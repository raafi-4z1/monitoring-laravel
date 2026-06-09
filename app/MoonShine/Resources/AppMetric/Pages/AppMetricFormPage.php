<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AppMetric\Pages;

use App\MoonShine\Resources\AppMetric\AppMetricResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Date;
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
                    Box::make('Data Metrik', [
                        Date::make('Timestamp', 'recorded_at')
                            ->withTime()
                            ->inputFormat("Y-m-d\TH:i:s")
                            ->format('d M Y H:i:s')
                            ->customAttributes(['step' => '1'])
                            ->default(now()->format('Y-m-d\TH:i:s'))
                            ->required()
                            ->hint('Diisi otomatis. Milidetik ditambahkan otomatis saat menyimpan.'),

                        Text::make('Nama Aplikasi', 'nama_aplikasi')
                            ->required()
                            ->placeholder('mis. MTELE, ENGINE-NOTIF, API-GATEWAY'),

                        Text::make('Metrik', 'metric')
                            ->required()
                            ->placeholder('mis. CPU, MEMORY, DISK, RESPONSE_TIME'),

                        Text::make('Value', 'value')
                            ->required()
                            ->placeholder('mis. 75, 2.4, 512'),

                        Text::make('Satuan', 'satuan')
                            ->required()
                            ->placeholder('mis. %, GB, MB, ms'),
                    ]),
                ])->columnSpan(6),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'recorded_at'   => 'required|date',
            'nama_aplikasi' => 'required|string|max:255',
            'metric'        => 'required|string|max:255',
            'value'         => 'required|string|max:255',
            'satuan'        => 'required|string|max:50',
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
