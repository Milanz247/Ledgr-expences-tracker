<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get user's categories and global categories (user_id = null)
        $categories = Category::where(function($query) use ($request) {
            $query->where('user_id', $request->user()->id)
                  ->orWhereNull('user_id');
        })->latest()->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'type' => 'required|in:income,expense',
        ]);

        $category = $request->user()->categories()->create($request->all());

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        // Ensure user can only update their own categories
        if ($category->user_id && $category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent editing global categories
        if (!$category->user_id) {
            return response()->json(['message' => 'Cannot edit default categories'], 422);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'type' => 'sometimes|required|in:income,expense',
        ]);

        $category->update($request->all());

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Category $category)
    {
        // Ensure user can only delete their own categories
        if ($category->user_id && $category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent deleting global categories
        if (!$category->user_id) {
            return response()->json(['message' => 'Cannot delete default categories'], 422);
        }

        // Check if category has linked expenses
        if ($category->expenses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete. This category has ' . $category->expenses()->count() . ' linked expense(s).'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
