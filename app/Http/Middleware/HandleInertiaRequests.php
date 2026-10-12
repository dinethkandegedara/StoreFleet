<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\BusinessSettingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authorizedBranches = collect();
        $activeBranch = null;

        if ($user && $user->isActive()) {
            if ($user->isSuperAdmin() || $user->hasRole('business-owner')) {
                $authorizedBranches = Branch::where('is_active', true)->orderBy('name')->get();
            } else {
                $hasGlobal = $user->branchUserRoles()
                    ->whereNull('branch_id')
                    ->whereHas('role', fn ($q) => $q->where('is_active', true))
                    ->exists();

                if ($hasGlobal) {
                    $authorizedBranches = Branch::where('is_active', true)->orderBy('name')->get();
                } else {
                    $assignedIds = $user->branchUserRoles()
                        ->whereNotNull('branch_id')
                        ->whereHas('role', fn ($q) => $q->where('is_active', true))
                        ->pluck('branch_id')
                        ->all();

                    $authorizedBranches = Branch::where('is_active', true)
                        ->whereIn('id', $assignedIds)
                        ->orderBy('name')
                        ->get();
                }
            }

            // Ensure active branch in session is valid and authorized
            $sessionBranchId = $request->session()->get('active_branch_id');
            if ($sessionBranchId && $authorizedBranches->contains('id', (int) $sessionBranchId)) {
                $activeBranch = $authorizedBranches->firstWhere('id', (int) $sessionBranchId);
            } elseif ($authorizedBranches->isNotEmpty()) {
                // Default to first authorized branch or default branch
                $default = $authorizedBranches->firstWhere('is_default', true) ?? $authorizedBranches->first();
                $activeBranch = $default;
                $request->session()->put('active_branch_id', $default->id);
            }
        }

        $businessService = app(BusinessSettingService::class);

        return [
            ...parent::share($request),
            'appName' => config('app.name', 'StoreFleet'),
            'business' => [
                'name' => $businessService->get('business_name', 'StoreFleet Merchant'),
                'logo' => $businessService->get('business_logo_path'),
                'primary_color' => $businessService->get('primary_color', '#2563eb'),
                'currency_code' => $businessService->get('currency_code', 'USD'),
                'currency_symbol' => $businessService->get('currency_symbol', '$'),
                'currency_precision' => (int) $businessService->get('currency_precision', 2),
                'timezone' => $businessService->get('timezone', 'America/New_York'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'theme_preference' => $user->theme_preference ?? 'light',
                ] : null,
                'active_branch' => $activeBranch ? [
                    'id' => $activeBranch->id,
                    'name' => $activeBranch->name,
                    'code' => $activeBranch->code,
                ] : null,
                'authorized_branches' => $authorizedBranches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'code' => $b->code,
                ])->values()->all(),
                'permissions' => fn () => $user
                    ? $user->getPermissions($activeBranch?->id)
                    : [],
                'roles' => fn () => $user
                    ? $user->branchUserRoles()
                        ->whereHas('role', fn ($q) => $q->where('is_active', true))
                        ->with('role')
                        ->get()
                        ->pluck('role.slug')
                        ->unique()
                        ->values()
                        ->all()
                    : [],
                'active_branch_id' => $activeBranch?->id,
            ],
        ];
    }
}
