<?php

namespace Modules\Products\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Role;
use Modules\Products\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);

        $this->client = User::factory()->create();
        $this->client->roles()->attach($clientRole);
    }

    /** @test */
    public function admin_can_create_category()
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $response = $this->postJson('/api/products/categories', [
            'name' => 'Accessories',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'Accessories']);
    }

    /** @test */
    public function admin_can_update_category()
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $category = Category::create(['name' => 'Old Name']);

        $response = $this->putJson("/api/products/categories/{$category->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['name' => 'New Name']);
    }

    /** @test */
    public function admin_can_delete_category()
    {
        Sanctum::actingAs($this->admin, ['admin']);
        $category = Category::create(['name' => 'Temporary']);

        $response = $this->deleteJson("/api/products/categories/{$category->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /** @test */
    public function client_cannot_create_category()
    {
        Sanctum::actingAs($this->client, ['client']);

        $response = $this->postJson('/api/products/categories', [
            'name' => 'Jewelry',
        ]);

        $response->assertStatus(403);
    }
/** @test */
public function guest_can_view_active_categories_only()
{
    Category::create(['name' => 'Active Cat', 'is_active' => true]);
    Category::create(['name' => 'Inactive Cat', 'is_active' => false]);

    $response = $this->getJson('/api/products/categories/active');

    $response->assertStatus(200)
             ->assertJsonCount(1, 'data') // check only one active category in paginated data
             ->assertJsonFragment(['name' => 'Active Cat'])
             ->assertJsonMissing(['name' => 'Inactive Cat']);
}

}
