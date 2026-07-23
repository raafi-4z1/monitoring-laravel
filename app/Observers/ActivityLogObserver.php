<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer generik — didaftarkan ke beberapa model sekaligus (lihat AppServiceProvider::boot()),
 * supaya tidak perlu instrumentasi manual di tiap Resource. Hanya menangkap perubahan yang lewat
 * Eloquent model event asli ($model->save()/->delete()) — mutasi lewat query builder mentah
 * (DB::table(), ::whereKey()->delete(), dsb) tidak lewat sini dan harus dicatat manual.
 */
class ActivityLogObserver
{
    public function created(Model $model): void
    {
        ActivityLogger::log('create', $this->describe($model, 'Membuat'), $model);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        ActivityLogger::log('update', $this->describe($model, 'Mengubah'), $model, ['changes' => $changes]);
    }

    public function deleted(Model $model): void
    {
        ActivityLogger::log('delete', $this->describe($model, 'Menghapus'), $model);
    }

    private function describe(Model $model, string $verb): string
    {
        $label = class_basename($model);
        $name  = $model->name ?? $model->nama ?? $model->label ?? $model->email ?? (string) $model->getKey();

        return "{$verb} {$label}: {$name}";
    }
}
