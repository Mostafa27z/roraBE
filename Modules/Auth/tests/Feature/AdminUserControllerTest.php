<?php

namespace Modules\Auth\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Modules\Auth\Models\Role;
use Laravel\Sanctum\Sanctum;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $clientRole = Role::factory()->create(['name' => 'client']);

        // Create an admin user
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);

        // Authenticate as admin
        Sanctum::actingAs($this->admin, ['*']);
    }

    /** @test */
    public function admin_can_list_users()
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/auth/admin/users');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);
    }

    /** @test */
    public function admin_can_create_user()
    {
        $role = Role::where('name', 'client')->first();

        $data = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'role' => $role->name,
        ];

        $response = $this->postJson('/api/auth/admin/users', $data);

        $response->assertStatus(200)
                 ->assertJsonFragment(['email' => 'newuser@example.com']);
    }

    /** @test */
    public function admin_can_view_specific_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/auth/admin/users/{$user->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $user->id]);
    }

    /** @test */
    public function admin_can_update_user()
    {
        $user = User::factory()->create();

        $response = $this->putJson("/api/auth/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['email' => 'updated@example.com']);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/auth/admin/users/{$user->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'User deleted successfully']);
    }
}
