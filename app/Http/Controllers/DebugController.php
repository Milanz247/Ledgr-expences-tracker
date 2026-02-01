<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DebugController extends Controller
{
    public function getLogs()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json(['message' => 'No log file found.']);
        }

        $content = File::get($logPath);
        
        // Get last 200 lines roughly
        $lines = explode("\n", $content);
        $lines = array_slice($lines, -200);
        
        return response(implode("\n", $lines))->header('Content-Type', 'text/plain');
    }

    public function clearCache()
    {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return "Cache cleared!";
    }

    public function testLog()
    {
        \Illuminate\Support\Facades\Log::info("TEST LOG ENTRY: The logging system is working.");
        return "Log attempted. Check file now.";
    }
}
