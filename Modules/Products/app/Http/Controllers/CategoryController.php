<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Products\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    
    public function index(Request $request)
    {
        $query = Category::query();

        // 🔍 Search by name
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 📄 Pagination (default 10)
        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }

    public function active(Request $request)
    {
        $query = Category::where('is_active', true);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function show($id)
    {
        return response()->json(Category::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name' => 'string|max:255',
            'is_active' => 'boolean',
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }

    
    // public function active()
    // {
    //     return response()->json(Category::where('is_active', true)->get());
    // }
}
