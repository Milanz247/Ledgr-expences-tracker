<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundSource;
use Illuminate\Http\Request;

class FundSourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $fundSources = $request->user()->fundSources()->latest()->get();
        return response()->json(['data' => $fundSources]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'source_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $fundSource = $request->user()->fundSources()->create($request->all());

        return response()->json($fundSource, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, FundSource $fundSource)
    {
        // Ensure user can only view their own fund sources
        if ($fundSource->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($fundSource);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FundSource $fundSource)
    {
        // Ensure user can only update their own fund sources
        if ($fundSource->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'source_name' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $fundSource->update($request->all());

        return response()->json($fundSource);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, FundSource $fundSource)
    {
        // Ensure user can only delete their own fund sources
        if ($fundSource->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if there are any expenses linked to this fund source
        if ($fundSource->expenses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete. This fund source has transaction history.'
            ], 422);
        }

        $fundSource->delete();

        return response()->json([
            'message' => 'Fund source deleted successfully',
        ]);
    }
}
