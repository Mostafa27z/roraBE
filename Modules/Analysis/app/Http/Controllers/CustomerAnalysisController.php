<?php

namespace Modules\Analysis\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Models\Order;
use App\Models\User;

class CustomerAnalysisController extends Controller
{
    public function index(): JsonResponse
    {
        // Only admin can access
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 1. Total customers
        $total_customers = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->count();

        // 2. Repeat customers (more than 1 order)
        $repeat_customers = Order::select('user_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('user_id')
            ->having('order_count', '>', 1)
            ->count();

        // 3. Average order per customer
        $total_orders = Order::count();
        $average_orders_per_customer = $total_customers > 0 ? round($total_orders / $total_customers, 2) : 0;

        // 4. Top customers by spending
        $top_customers = Order::select('user_id', DB::raw('SUM(total) as total_spent'))
            ->groupBy('user_id')
            ->orderByDesc('total_spent')
            ->take(5)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($o) => [
                'id' => $o->user->id,
                'name' => $o->user->name,
                'email' => $o->user->email,
                'total_spent' => $o->total_spent,
            ]);

        return response()->json([
            'overview' => [
                'total_customers' => $total_customers,
                'repeat_customers' => $repeat_customers,
                'average_orders_per_customer' => $average_orders_per_customer,
            ],
            'top_customers' => $top_customers,
        ]);
    }
}
