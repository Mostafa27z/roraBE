<?php

namespace Modules\Orders\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    //  Client: Place new order
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $order = Order::create([
            'order_number' => strtoupper(Str::random(10)),
            'user_id' => Auth::id(),
            'status' => 'pending',
            'total' => 0,
        ]);

        $total = 0;

        foreach ($request->items as $item) {
            $price = \Modules\Products\Models\Product::find($item['product_id'])->price;
            $subtotal = $price * $item['quantity'];
            $total += $subtotal;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $price,
            ]);
        }

        $order->update(['total' => $total]);

        return response()->json(['message' => 'Order placed successfully', 'order' => $order->load('items')], 201);
    }

    //  Client: View my orders
    public function myOrders()
    {
        $orders = Order::with('items.product')
            ->where('user_id', Auth::id())
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    // Admin: View all orders
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('id', 'desc')->paginate(10);
        return response()->json($orders);
    }

    //  Admin: Update order status
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,canceled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated', 'order' => $order]);
    }
}
