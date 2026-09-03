<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['settings' => Setting::query()->pluck('value', 'key')]);
    }

    public function update(Request $request, AuditService $auditService): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:255'],
            'facility_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:10000'],
            'expiration_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            'date_format' => ['required', 'in:M d, Y,d/m/Y,m/d/Y,Y-m-d'],
            'timezone' => ['required', 'timezone'],
            'notification_low_stock' => ['required', 'boolean'],
            'notification_expiration' => ['required', 'boolean'],
            'default_theme' => ['required', 'in:light,dark,system'],
        ]);

        $booleanKeys = ['notification_low_stock', 'notification_expiration'];
        $integerKeys = ['low_stock_threshold', 'expiration_warning_days'];

        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => in_array($key, $booleanKeys, true) ? ($request->boolean($key) ? '1' : '0') : (string) $value,
                    'type' => match (true) {
                        in_array($key, $booleanKeys, true) => 'boolean',
                        in_array($key, $integerKeys, true) => 'integer',
                        default => 'string',
                    },
                    'group' => in_array($key, ['system_name', 'facility_name', 'contact_phone', 'contact_email', 'address'], true) ? 'general' : 'inventory',
                    'is_public' => in_array($key, ['system_name', 'facility_name'], true),
                ],
            );
        }

        $auditService->record('settings_updated', 'Updated system settings.', null, null, $data);

        return back()->with('success', 'Settings saved successfully.');
    }
}
