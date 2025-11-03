<?php

namespace Modules\Analysis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Products\Models\Product;
use Modules\Products\Models\Category;
use Illuminate\Support\Facades\DB;

class SalesAnalysisController extends Controller
{
    /**
     * Display main sales statistics.
     */
    public function index(Request $request)
    {
        // Total sales overview
        $totalSales = Order::where('status', 'completed')->sum('total');
        $totalOrders = Order::where('status', 'completed')->count();
        $avgOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Sales trend (monthly)
        if (config('database.default') === 'sqlite') {
    $salesTrend = Order::selectRaw("strftime('%m', created_at) as month, SUM(total) as total_sales")
        ->where('status', 'completed')
        ->groupByRaw("strftime('%m', created_at)")
        ->orderBy('month')
        ->get();
} else {
    $salesTrend = Order::selectRaw('MONTH(created_at) as month, SUM(total) as total_sales')
        ->where('status', 'completed')
        ->groupByRaw('MONTH(created_at)')
        ->orderBy('month')
        ->get();
}


        // Top 5 selling products
        $topProducts = OrderItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(quantity * price) as total_revenue')
            )
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->with('product:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'product_name' => $item->product->name ?? 'Unknown',
                    'total_sold' => $item->total_sold,
                    'total_revenue' => $item->total_revenue
                ];
            });

        // Category performance
        $categoryPerformance = Category::select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('order_items', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json([
            'overview' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'average_order_value' => $avgOrderValue,
            ],
            'sales_trend' => $salesTrend,
            'top_products' => $topProducts,
            'category_performance' => $categoryPerformance,
        ]);
    }
}
