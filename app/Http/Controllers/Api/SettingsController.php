<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get settings by group
     */
    public function show(Request $request, $group)
    {
        $setting = Setting::where('user_id', $request->user()->id)
            ->where('group', $group)
            ->first();

        if (!$setting) {
            return response()->json(['payload' => []]);
        }

        return response()->json($setting);
    }

    /**
     * Update settings for a group
     */
    public function update(Request $request, $group)
    {
        // Treat the entire request body as the payload (excluding system keys)
        $data = $request->except(['_token', '_method']);

        $setting = Setting::updateOrCreate(
            ['user_id' => $request->user()->id, 'group' => $group],
            ['payload' => $data]
        );
        
        return response()->json($setting);
    }
}
