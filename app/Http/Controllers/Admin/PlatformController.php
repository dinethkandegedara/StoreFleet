<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    /**
     * Display the privileged Super Admin platform management interface.
     */
    public function index(Request $request): Response
    {
        $dbDriver = config('database.default', 'sqlite');
        $dbConnected = false;
        $dbVersion = 'Unknown';

        try {
            $pdo = DB::connection()->getPdo();
            $dbConnected = true;
            $dbVersion = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        // Storage writeability checks
        $storagePaths = [
            'storage/app' => is_writable(storage_path('app')),
            'storage/framework' => is_writable(storage_path('framework')),
            'storage/logs' => is_writable(storage_path('logs')),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
        ];

        // Required PHP extensions
        $requiredExtensions = [
            'bcmath' => extension_loaded('bcmath'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'pdo' => extension_loaded('pdo'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml' => extension_loaded('xml'),
        ];

        // Image pipeline status
        $gdInfo = function_exists('gd_info') ? gd_info() : [];
        $webpSupported = ! empty($gdInfo['WebP Support']);

        // Super Admin accounts
        $superAdmins = User::where('is_super_admin', true)
            ->get(['id', 'name', 'email', 'two_factor_confirmed_at', 'created_at'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'has_2fa' => ! empty($u->two_factor_confirmed_at),
                'created_at' => $u->created_at?->format('M d, Y'),
            ]);

        // Aggregate statistics
        $stats = [
            'total_users' => User::count(),
            'total_branches' => Branch::count(),
            'total_products' => Product::count(),
            'total_sales' => Sale::count(),
            'total_audit_logs' => AuditLog::count(),
        ];

        return Inertia::render('Admin/Platform/Index', [
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname('s'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'timezone' => config('app.timezone'),
                'app_env' => app()->environment(),
                'debug_mode' => config('app.debug'),
                'session_driver' => config('session.driver'),
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'db_driver' => $dbDriver,
                'db_connected' => $dbConnected,
                'db_version' => $dbVersion,
                'webp_supported' => $webpSupported,
            ],
            'storage_health' => $storagePaths,
            'extensions' => $requiredExtensions,
            'super_admins' => $superAdmins,
            'stats' => $stats,
        ]);
    }

    /**
     * Clear application cache.
     */
    public function clearCache(Request $request): RedirectResponse
    {
        Artisan::call('cache:clear');

        AuditLog::record('platform.cache_cleared', [
            'cleared_by' => $request->user()->name,
        ], $request->user());

        return back()->with('success', 'Application cache has been cleared successfully.');
    }
}
