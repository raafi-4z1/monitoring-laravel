<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Tambah role "User" (id=2) jika belum ada
        if (! DB::table('moonshine_user_roles')->where('id', 2)->exists()) {
            DB::table('moonshine_user_roles')->insert([
                'id'         => 2,
                'name'       => 'User',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('moonshine_user_role_id')->default(2)->after('email');
            $table->string('avatar')->nullable()->after('moonshine_user_role_id');

            $table->foreign('moonshine_user_role_id')
                ->references('id')
                ->on('moonshine_user_roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // Migrasi semua moonshine_users ke users (admin + user lain di moonshine)
        $moonshineUsers = DB::table('moonshine_users')->get();
        foreach ($moonshineUsers as $mu) {
            $exists = DB::table('users')->where('email', $mu->email)->exists();
            if (! $exists) {
                DB::table('users')->insert([
                    'name'                    => $mu->name,
                    'email'                   => $mu->email,
                    'password'                => $mu->password,
                    'moonshine_user_role_id'  => $mu->moonshine_user_role_id,
                    'avatar'                  => $mu->avatar ?? null,
                    'created_at'              => $mu->created_at ?? now(),
                    'updated_at'              => $mu->updated_at ?? now(),
                ]);
            } else {
                // Kalau email sudah ada, pastikan role disesuaikan (admin tetap admin)
                DB::table('users')
                    ->where('email', $mu->email)
                    ->update(['moonshine_user_role_id' => $mu->moonshine_user_role_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['moonshine_user_role_id']);
            $table->dropColumn(['moonshine_user_role_id', 'avatar']);
        });

        DB::table('moonshine_user_roles')->where('id', 2)->delete();
    }
};
