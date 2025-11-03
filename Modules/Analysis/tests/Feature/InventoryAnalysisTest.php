<?php

namespace Modules\Analysis\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Modules\Auth\Models\Role;
use Modules\Products\Models\Product;
use Modules\Products\Models\Category;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Models\Order;

class InventoryAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $clientRole = Role::factory()->create(['name' => 'client']);

        // Admin user
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);

        // Auth as admin
        Sanctum::actingAs($this->admin, ['*']);
    }

    /** @test */
    public function admin_can_view_inventory_analysis()
    {
        $category = Category::factory()->create(['name' => 'Electronics']);

        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Laptop',
            'stock_quantity' => 10,
            'price' => 1000,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mouse',
            'stock_quantity' => 3,
            'price' => 50,
        ]);

        // Orders
        $order = Order::factory()->create(['status' => 'completed']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'price' => 1000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 10,
            'price' => 50,
        ]);

        $response = $this->getJson('/api/analysis/inventory');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'overview' => [
                         'total_products',
                         'total_stock',
                         'average_stock',
                         'low_stock_products',
                     ],
                     'top_selling',
                     'category_distribution',
                 ]);
    }

    /** @test */
    public function client_cannot_access_inventory_analysis()
    {
        $clientRole = Role::where('name', 'client')->first() ?? Role::factory()->create(['name' => 'client']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole->id);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/analysis/inventory');

        $response->assertStatus(403);
    }
}
