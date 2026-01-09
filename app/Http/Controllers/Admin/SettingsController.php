<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = AdminSetting::all()->keyBy('key');

        return response()->json([
            'settings' => $settings,
            'commission_rate' => AdminSetting::getCommissionRate(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
        ]);

        $updated = [];

        if (isset($validated['commission_rate'])) {
            AdminSetting::setCommissionRate($validated['commission_rate']);
            $updated['commission_rate'] = $validated['commission_rate'];
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'updated' => $updated,
        ]);
    }
}
