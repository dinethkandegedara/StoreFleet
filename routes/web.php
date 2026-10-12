<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $dbConnected = false;
    $dbDriver = config('database.default', 'sqlite');

    try {
        DB::connection()->getPdo();
        $dbConnected = true;
    } catch (Throwable $e) {
        $dbConnected = false;
    }

    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'StoreFleet'),
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
        'dbDriver' => $dbDriver,
        'dbConnected' => $dbConnected,
        'appEnv' => app()->environment(),
    ]);
})->name('welcome');
