<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\BusinessSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingController extends Controller
{
    public function __construct(
        protected BusinessSettingService $service
    ) {}

    /**
     * Show business configuration and settings view.
     */
    public function edit(Request $request): Response
    {
        $settings = $this->service->getAll();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * Update business-wide settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:50'],
            'primary_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'currency_code' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_precision' => ['required', 'integer', 'min:0', 'max:4'],
            'currency_symbol_position' => ['required', 'in:before,after'],
            'decimal_separator' => ['required', Rule::in(['.', ','])],
            'thousand_separator' => ['nullable', Rule::in(['.', ',', ' ', 'none', ''])],
            'timezone' => ['required', 'string', 'timezone'],
            'date_format' => ['required', 'string', 'max:20'],
            'time_format' => ['required', 'string', 'max:20'],
            'tax_enabled' => ['required', 'boolean'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_id_number' => ['nullable', 'string', 'max:100'],
            'prices_include_tax' => ['required', 'boolean'],
            'receipt_header' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'receipt_show_logo' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp,svg'],
        ]);

        // Process logo upload if supplied
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('branding', 'public');
            $validated['business_logo_path'] = '/storage/'.$logoPath;
        }

        unset($validated['logo']);

        $this->service->updateMany($validated);

        AuditLog::record('business_settings_updated', [
            'updated_keys' => array_keys($validated),
            'business_name' => $validated['business_name'],
        ], $request->user());

        return back()->with('success', 'Business settings updated successfully.');
    }

    /**
     * Update user-specific theme preference.
     */
    public function updateUserPreference(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_preference' => ['required', 'in:light,dark'],
        ]);

        $user = $request->user();
        $user->update([
            'theme_preference' => $validated['theme_preference'],
        ]);

        return back()->with('success', 'User theme preference saved.');
    }
}
