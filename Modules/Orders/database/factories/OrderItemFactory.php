<?php

namespace Modules\Orders\Database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Models\Order;
use Modules\Products\Models\Product;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'price' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}
