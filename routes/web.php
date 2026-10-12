<?php

use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Models\Branch;
use Illuminate\Http\Request;
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
    Route::post('/switch-branch/{branch}', function (Branch $branch, Request $request) {
        if (! $branch->is_active) {
            return back()->with('error', 'Cannot switch to an inactive branch.');
        }
        $request->session()->put('active_branch_id', $branch->id);

        return back()->with('success', "Switched branch to {$branch->name}.");
    })->name('branch.switch');

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
