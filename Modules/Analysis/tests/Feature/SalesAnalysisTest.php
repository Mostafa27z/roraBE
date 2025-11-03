<?php

namespace Modules\Analysis\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Modules\Auth\Models\Role;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Products\Models\Product;
use Modules\Products\Models\Category;

class SalesAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create or find roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);

        // Authenticate as admin
        Sanctum::actingAs($this->admin, ['*']);
    }

    /** @test */
    public function admin_can_view_sales_analysis()
    {
        // Create category and product
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
        ]);

        // Create completed order
        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 200,
        ]);

        // Add order item
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100,
        ]);

        // Send request as admin
        $response = $this->getJson('/api/analysis/sales');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'overview' => [
                         'total_sales',
                         'total_orders',
                         'average_order_value',
                     ],
                     'sales_trend',
                     'top_products',
                     'category_performance',
                 ]);
    }

    /** @test */
    public function client_cannot_access_sales_analysis()
    {
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole->id);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/analysis/sales');

        $response->assertStatus(403);
    }
}
