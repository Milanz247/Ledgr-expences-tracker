<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get expenses grouped by category for the current month
     */
    public function expensesByCategory(Request $request)
    {
        $user = $request->user();

        // Define color palette for categories
        $colors = [
            '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
            '#06b6d4', '#6366f1', '#f97316', '#14b8a6', '#a855f7',
            '#ef4444', '#f43f5e', '#eab308', '#22c55e', '#0ea5e9'
        ];

        // Get expenses for current month grouped by category
        $expensesByCategory = $user->expenses()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category')
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item, $index) use ($colors) {
                return [
                    'category' => $item->category->name,
                    'amount' => floatval($item->total),
                    'fill' => $colors[$index % count($colors)],
                ];
            })
            ->values();

        return response()->json($expensesByCategory);
    }

    /**
     * Get expenses grouped by category for a specific month
     */
    public function expensesByCategoryMonth(Request $request)
    {
        $user = $request->user();

        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        // Define color palette for categories
        $colors = [
            '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
            '#06b6d4', '#6366f1', '#f97316', '#14b8a6', '#a855f7',
            '#ef4444', '#f43f5e', '#eab308', '#22c55e', '#0ea5e9'
        ];

        // Get expenses for specified month grouped by category
        $expensesByCategory = $user->expenses()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item, $index) use ($colors) {
                return [
                    'category' => $item->category->name,
                    'amount' => floatval($item->total),
                    'fill' => $colors[$index % count($colors)],
                ];
            })
            ->values();

        return response()->json($expensesByCategory);
    }
}
