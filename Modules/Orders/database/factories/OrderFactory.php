<?php

namespace Modules\Orders\Database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed']),
            'total' => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
