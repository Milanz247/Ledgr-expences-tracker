<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReportSetting;

class ReportSettingController extends Controller
{
    /**
     * Get report settings for the authenticated user.
     */
    public function show(Request $request)
    {
        $settings = $request->user()->reportSettings()->first();

        // Return empty object if no settings exist, or create default
        if (!$settings) {
            return response()->json([
                'is_enabled' => false,
                'frequency' => 'daily',
                'daily_report_time' => '18:00', // Default 6 PM
                'telegram_topic_id' => null
            ]);
        }

        return response()->json($settings);
    }

    /**
     * Update report settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'is_enabled' => 'boolean',
            'daily_report_time' => 'nullable|date_format:H:i',
            'telegram_topic_id' => 'nullable|string',
        ]);

        $settings = $request->user()->reportSettings()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['report_email' => $request->user()->email] // Default email if creating
        );

        $settings->update($validated);

        return response()->json($settings);
    }
}
