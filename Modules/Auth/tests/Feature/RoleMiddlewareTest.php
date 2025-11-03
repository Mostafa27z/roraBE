<?php

namespace Modules\Auth\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Modules\Auth\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_denies_access_if_user_does_not_have_required_role()
    {
        $role = Role::create(['name' => 'client']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $token = $user->createToken('auth_token', ['client'])->plainTextToken;

        // Create a route that uses RoleMiddleware for 'admin'
        $this->app['router']->get('/test-admin', function () {
            return response()->json(['message' => 'Welcome admin']);
        })->middleware(['auth:sanctum', 'role:admin']);

        $response = $this->getJson('/test-admin', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized']);
    }

    /** @test */
    public function it_allows_access_if_user_has_required_role()
    {
        $role = Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $token = $user->createToken('auth_token', ['admin'])->plainTextToken;

        // Same route but user is admin now
        $this->app['router']->get('/test-admin', function () {
            return response()->json(['message' => 'Welcome admin']);
        })->middleware(['auth:sanctum', 'role:admin']);

        $response = $this->getJson('/test-admin', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Welcome admin']);
    }
}
