<?php

namespace Modules\Products\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Role;
use Modules\Products\Models\Product;
use Modules\Products\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);

        $this->client = User::factory()->create();
        $this->client->roles()->attach($clientRole);

        // Create sample category
        $this->category = Category::create(['name' => 'Makeup']);
    }

    /** @test */
    public function admin_can_create_product()
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'name' => 'Lipstick',
            'price' => 100,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Lipstick']);
    }

    /** @test */
    public function admin_can_update_product()
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Name',
            'price' => 150,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', ['name' => 'Updated Name']);
    }

    /** @test */
    public function admin_can_delete_product()
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $response = $this->deleteJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test */
    public function client_cannot_create_product()
    {
        Sanctum::actingAs($this->client, ['client']);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'name' => 'Eyeliner',
            'price' => 80,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
public function guest_can_search_active_products()
{
    Product::factory()->create(['name' => 'Lipstick', 'is_active' => true]);
    Product::factory()->create(['name' => 'Eyeliner', 'is_active' => true]);

    $response = $this->getJson('/api/products/active?search=Lip');
    $response->assertStatus(200)
             ->assertJsonFragment(['name' => 'Lipstick'])
             ->assertJsonMissing(['name' => 'Eyeliner']);
}

}
