<?php

namespace Modules\Analysis\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Products\Models\Product;
use Modules\Products\Models\Category;
use Modules\Orders\Models\OrderItem;

class InventoryAnalysisController extends Controller
{
    public function index(): JsonResponse
    {
        // Only admin can access
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Overview
        $total_products = Product::count();
        $total_stock = Product::sum('stock_quantity');
        $average_stock = $total_products > 0 ? round($total_stock / $total_products, 2) : 0;
        $low_stock_products = Product::where('stock_quantity', '<', 5)->count();

        // Top selling products
        $top_selling = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->with('product:id,name,stock_quantity')
            ->get()
            ->map(fn($item) => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'stock_quantity' => $item->product->stock_quantity,
                'total_sold' => $item->total_sold,
            ]);

        // Category-wise stock distribution
        $category_distribution = Category::select('name')
            ->withSum('products as total_stock', 'stock_quantity')
            ->get()
            ->map(fn($cat) => [
                'category' => $cat->name,
                'total_stock' => $cat->total_stock ?? 0,
            ]);

        return response()->json([
            'overview' => [
                'total_products' => $total_products,
                'total_stock' => $total_stock,
                'average_stock' => $average_stock,
                'low_stock_products' => $low_stock_products,
            ],
            'top_selling' => $top_selling,
            'category_distribution' => $category_distribution,
        ]);
    }
}
