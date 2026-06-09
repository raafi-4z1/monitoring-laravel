<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE app_metrics MODIFY recorded_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE app_metrics MODIFY recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
    }
};
