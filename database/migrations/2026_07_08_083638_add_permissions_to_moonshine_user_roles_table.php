<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('moonshine_user_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('moonshine_user_roles', 'permissions')) {
                $table->json('permissions')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moonshine_user_roles', function (Blueprint $table) {
            if (Schema::hasColumn('moonshine_user_roles', 'permissions')) {
                $table->dropColumn('permissions');
            }
        });
    }
};
