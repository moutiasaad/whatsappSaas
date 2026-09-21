<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin CRUD for countries the platform sells in.
 *
 * Adding a country enables per-country pricing on every plan
 * (see the "Country prices" section on /platform/plans/{id}/edit).
 * `code` is ISO 3166-1 alpha-2 and joins directly on Cloudflare's
 * CF-IPCountry header, so the picker on the customer side maps to
 * the right price without an intermediate lookup table.
 */
class CountriesController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return view('admin.platform.countries', [
            'countries' => Country::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return view('admin.platform.country-form', ['country' => null]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $this->validated($request);

        $country = Country::create($data);

        AuditLog::record('country.created', $country, ['code' => $country->code]);

        return redirect()->route('super_admin.platform.countries.index')
            ->with('success', __('ui.platform_countries_page.created', ['name' => $country->name]));
    }

    public function edit(Country $country)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return view('admin.platform.country-form', compact('country'));
    }

    public function update(Request $request, Country $country)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $this->validated($request, $country->id);

        $country->update($data);

        AuditLog::record('country.updated', $country, ['code' => $country->code]);

        return redirect()->route('super_admin.platform.countries.index')
            ->with('success', __('ui.platform_countries_page.updated', ['name' => $country->name]));
    }

    public function toggle(Country $country)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $country->update(['is_active' => ! $country->is_active]);

        AuditLog::record('country.toggled', $country, ['is_active' => $country->is_active]);

        return back()->with('success', __('ui.platform_countries_page.toggled', [
            'name'   => $country->name,
            'status' => $country->is_active ? __('ui.enabled') : __('ui.disabled'),
        ]));
    }

    public function destroy(Country $country)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $name = $country->name;
        $country->delete(); // plan_country_prices cascade

        AuditLog::record('country.deleted', null, ['name' => $name]);

        return redirect()->route('super_admin.platform.countries.index')
            ->with('success', __('ui.platform_countries_page.deleted', ['name' => $name]));
    }

    /**
     * Validation shared by store + update. Country code is uppercased
     * before persistence so CF-IPCountry header (always uppercase) joins
     * cleanly, and duplicate-detection is case-insensitive.
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'code'          => strtoupper(trim((string) $request->input('code'))),
            'currency_code' => strtoupper(trim((string) $request->input('currency_code'))),
        ]);

        return $request->validate([
            'code'            => ['required', 'string', 'size:2', 'alpha', Rule::unique('countries', 'code')->ignore($ignoreId)],
            'name'            => ['required', 'string', 'max:100'],
            'currency_code'   => ['required', 'string', 'size:3', 'alpha'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'is_active'       => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
