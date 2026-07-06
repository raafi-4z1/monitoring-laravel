<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role "User" (id=2) dibutuhkan sebagai default FK di tabel users
        if (! DB::table('moonshine_user_roles')->where('id', 2)->exists()) {
            DB::table('moonshine_user_roles')->insert([
                'id'         => 2,
                'name'       => 'User',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('moonshine_user_role_id')->default(2)->after('email');
            $table->string('avatar')->nullable()->after('moonshine_user_role_id');

            $table->foreign('moonshine_user_role_id')
                ->references('id')->on('moonshine_user_roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['moonshine_user_role_id']);
            $table->dropColumn(['moonshine_user_role_id', 'avatar']);
        });

        DB::table('moonshine_user_roles')->where('id', 2)->delete();
    }
};
