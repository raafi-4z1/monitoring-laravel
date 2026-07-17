<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ReportSource\Pages;

use App\Models\ReportSource;
use App\MoonShine\Resources\ReportSource\ReportSourceResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<ReportSourceResource>
 */
class ReportSourceFormPage extends FormPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make('Report Source', [
                        ID::make(),
                        Text::make('Service Name', 'service_name')
                            ->required()
                            ->placeholder('mis. trx_pbi_limit')
                            ->readonly($this->isItemExists())
                            ->hint(
                                $this->isItemExists()
                                    ? 'Tidak bisa diubah — dipakai sebagai kunci link ke kode (Service class). Mengubahnya akan memutus link data lama & fetch berikutnya.'
                                    : 'Snake_case, unik per layanan. Tidak bisa diubah lagi setelah disimpan.'
                            ),
                        Text::make('App ID', 'app_id')
                            ->nullable()
                            ->placeholder('mis. AFOAFO0252'),
                        Select::make('Data Source', 'data_source')
                            ->options([
                                'ELK'      => 'ELK',
                                'Dynatrace' => 'Dynatrace',
                                'DBMS'     => 'DBMS',
                            ])
                            ->required(),
                        Text::make('Data Source Name', 'data_source_name')
                            ->required()
                            ->placeholder('mis. wic-trx-pbi-ceklimit*'),
                        Text::make('Service Integrator', 'service_integrator')
                            ->nullable()
                            ->placeholder('mis. WIC'),
                        Text::make('Host IP', 'host_ip')
                            ->nullable()
                            ->placeholder('mis. 192.168.6.3')
                            ->hint('Hanya dipakai untuk sumber data yang query-nya difilter per-host (mis. WIC Metric). Kosongkan kalau tidak relevan.'),
                        Text::make('Kode Prefix', 'kode_prefix')
                            ->nullable()
                            ->placeholder('mis. BP, SPI')
                            ->hint('Digunakan sebagai prefix nama file CSV export.'),
                    ]),
                ])->columnSpan(8),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'service_name'       => [
                'required',
                'string',
                'max:50',
                Rule::unique('report_sources', 'service_name')->ignore($item->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($item): void {
                    if ($item->getKey() === null) {
                        return;
                    }

                    $original = ReportSource::find($item->getKey())?->service_name;

                    if ($original !== null && $original !== $value) {
                        $fail('Service Name tidak dapat diubah setelah dibuat — dipakai sebagai kunci link ke kode (Service class).');
                    }
                },
            ],
            'app_id'             => 'nullable|string|max:50',
            'data_source'        => 'required|string|max:50',
            'data_source_name'   => 'required|string|max:100',
            'service_integrator' => 'nullable|string|max:50',
            'host_ip'            => 'nullable|string|max:45',
            'kode_prefix'        => 'nullable|string|max:20',
        ];
    }

    protected function topLayer(): array    { return [...parent::topLayer()]; }
    protected function mainLayer(): array   { return [...parent::mainLayer()]; }
    protected function bottomLayer(): array { return [...parent::bottomLayer()]; }
}
