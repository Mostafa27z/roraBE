<?php

namespace Modules\Orders\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Orders\Models\Order;
use Modules\Products\Models\Product;
use App\Models\User;
use Modules\Auth\Models\Role;
use Laravel\Sanctum\Sanctum;

class OrdersControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $clientRole = Role::factory()->create(['name' => 'client']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);

        $this->client = User::factory()->create();
        $this->client->roles()->attach($clientRole);
    }

    /** @test */
    public function client_can_place_order()
    {
        Sanctum::actingAs($this->client);

        $product = Product::factory()->create();

        $data = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $data);

        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Order placed successfully']);
    }

    /** @test */
    public function client_can_view_own_orders()
    {
        Sanctum::actingAs($this->client);

        Order::factory()->count(2)->create(['user_id' => $this->client->id]);

        $response = $this->getJson('/api/orders/my');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    /** @test */
    public function admin_can_view_all_orders()
    {
        Sanctum::actingAs($this->admin);

        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    /** @test */
    public function admin_can_update_order_status()
    {
        Sanctum::actingAs($this->admin);

        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->putJson("/api/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'completed']);
    }
}
