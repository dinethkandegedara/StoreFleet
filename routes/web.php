<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessSettingController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Models\Branch;
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

// RBAC Protected Administrative & Operational Routes
Route::middleware(['auth', 'active'])->group(function () {
    // Branch switching for active context
    Route::post('/switch-branch/{branch}', [BranchController::class, 'switchBranch'])->name('branch.switch');

    // User preference update
    Route::post('/user/preference/theme', [BusinessSettingController::class, 'updateUserPreference'])->name('user.preference.theme');

    // Business Settings Configuration
    Route::prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [BusinessSettingController::class, 'edit'])->middleware('permission:settings.view')->name('edit');
        Route::put('/', [BusinessSettingController::class, 'update'])->middleware('permission:settings.edit')->name('update');
    });

    // Branch Management
    Route::prefix('admin/branches')->name('admin.branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->middleware('permission:branches.view')->name('index');
        Route::get('/create', [BranchController::class, 'create'])->middleware('permission:branches.create')->name('create');
        Route::post('/', [BranchController::class, 'store'])->middleware('permission:branches.create')->name('store');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:branches.edit')->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.edit')->name('update');
        Route::post('/{branch}/toggle-active', [BranchController::class, 'toggleActive'])->middleware('permission:branches.manage')->name('toggle-active');
    });

    // Role & Permission Management (RBAC)
    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('index');
        Route::get('/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('update');
        Route::post('/{role}/duplicate', [RoleController::class, 'duplicate'])->middleware('permission:roles.create')->name('duplicate');
        Route::post('/{role}/toggle-active', [RoleController::class, 'toggleActive'])->middleware('permission:roles.edit')->name('toggle-active');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');

        // Role Assignments
        Route::get('/assignments/manage', [RoleAssignmentController::class, 'index'])->middleware('permission:roles.assign')->name('assignments.index');
        Route::post('/assignments', [RoleAssignmentController::class, 'store'])->middleware('permission:roles.assign')->name('assignments.store');
        Route::delete('/assignments/{assignment}', [RoleAssignmentController::class, 'destroy'])->middleware('permission:roles.assign')->name('assignments.destroy');
    });

    // Sample branch-scoped protected route for testing cross-branch enforcement
    Route::get('/branches/{branch_id}/catalog', function ($branch_id) {
        return response()->json(['status' => 'authorized', 'branch_id' => (int) $branch_id]);
    })->middleware('permission:catalog.products.view')->name('branches.catalog');
});

require __DIR__.'/auth.php';
