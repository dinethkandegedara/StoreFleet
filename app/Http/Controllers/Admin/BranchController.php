<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request): Response
    {
        $branches = Branch::withCount('branchUserRoles as users_count')
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'city' => $branch->city,
                'state' => $branch->state,
                'postal_code' => $branch->postal_code,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'manager_name' => $branch->manager_name,
                'receipt_header' => $branch->receipt_header,
                'receipt_footer' => $branch->receipt_footer,
                'is_active' => $branch->is_active,
                'is_default' => $branch->is_default,
                'users_count' => $branch->users_count,
            ]);

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    /**
     * Show form for creating a new branch.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Branches/Form', [
            'branch' => null,
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_num', 'unique:branches,code'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ]);

        $validated['code'] = Str::upper($validated['code']);

        $branch = DB::transaction(function () use ($validated, $request) {
            if (! empty($validated['is_default'])) {
                Branch::where('is_default', true)->update(['is_default' => false]);
            }

            $branch = Branch::create([
                ...$validated,
                'is_active' => true,
            ]);

            AuditLog::record('branch_created', [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
            ], $request->user());

            return $branch;
        });

        return redirect()->route('admin.branches.index')
            ->with('success', "Branch '{$branch->name}' created successfully.");
    }

    /**
     * Show form for editing an existing branch.
     */
    public function edit(Branch $branch): Response
    {
        return Inertia::render('Admin/Branches/Form', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'city' => $branch->city,
                'state' => $branch->state,
                'postal_code' => $branch->postal_code,
                'country' => $branch->country,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'manager_name' => $branch->manager_name,
                'receipt_header' => $branch->receipt_header,
                'receipt_footer' => $branch->receipt_footer,
                'is_active' => $branch->is_active,
                'is_default' => $branch->is_default,
            ],
        ]);
    }

    /**
     * Update an existing branch.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_num', 'unique:branches,code,'.$branch->id],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ]);

        $validated['code'] = Str::upper($validated['code']);

        DB::transaction(function () use ($branch, $validated, $request) {
            if (! empty($validated['is_default']) && ! $branch->is_default) {
                Branch::where('is_default', true)->update(['is_default' => false]);
            }

            $branch->update($validated);

            AuditLog::record('branch_updated', [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
            ], $request->user());
        });

        return redirect()->route('admin.branches.index')
            ->with('success', "Branch '{$branch->name}' updated successfully.");
    }

    /**
     * Controlled deactivation or activation of a branch.
     */
    public function toggleActive(Request $request, Branch $branch): RedirectResponse
    {
        // Controlled deactivation check: cannot deactivate the only active branch
        if ($branch->is_active) {
            $otherActiveCount = Branch::where('is_active', true)
                ->where('id', '!=', $branch->id)
                ->count();

            if ($otherActiveCount === 0) {
                return back()->with('error', 'Cannot deactivate the only active branch. The business must have at least one operating location.');
            }
        }

        $branch->update([
            'is_active' => ! $branch->is_active,
        ]);

        AuditLog::record('branch_status_toggled', [
            'branch_id' => $branch->id,
            'branch_code' => $branch->code,
            'is_active' => $branch->is_active,
        ], $request->user());

        $status = $branch->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Branch '{$branch->name}' has been {$status}.");
    }

    /**
     * Switch user's active branch with server-side authorization validation.
     */
    public function switchBranch(Request $request, Branch $branch): RedirectResponse
    {
        $user = $request->user();

        if (! $branch->is_active) {
            return back()->with('error', 'Cannot switch to an inactive branch.');
        }

        // Authorization check: User must be Super Admin, Business Owner, or have an assignment for this branch (or globally)
        $isAuthorized = false;
        if ($user->isSuperAdmin() || $user->hasRole('business-owner')) {
            $isAuthorized = true;
        } else {
            $isAuthorized = $user->branchUserRoles()
                ->where(function ($q) use ($branch) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
                })
                ->whereHas('role', fn ($q) => $q->where('is_active', true))
                ->exists();
        }

        if (! $isAuthorized) {
            AuditLog::record('unauthorized_branch_switch_attempt', [
                'target_branch_id' => $branch->id,
                'target_branch_code' => $branch->code,
            ], $user);

            return back()->with('error', 'You are not authorized to access this branch.');
        }

        $request->session()->put('active_branch_id', $branch->id);

        return back()->with('success', "Switched active branch to {$branch->name}.");
    }
}
