<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/' . config('moonshine.prefix', 'admin')));
