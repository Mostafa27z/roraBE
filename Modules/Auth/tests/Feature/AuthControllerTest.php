<?php

namespace Modules\Auth\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Modules\Auth\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;
protected function setUp(): void
{
    parent::setUp();
    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
}
    protected string $baseUrl = '/api/auth'; // ✅ add base URL once

    /** @test */
    public function user_can_register_with_default_client_role()
    {
        Role::create(['name' => 'client']);

        $response = $this->postJson("{$this->baseUrl}/register", [
            'name' => 'Rora Tester',
            'email' => 'rora@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'roles'],
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'rora@example.com']);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_login_and_receive_token()
    {
        $role = Role::create(['name' => 'client']);
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $user->roles()->attach($role);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'user' => ['id', 'roles'],
                 ]);
    }

    /** @test */
    public function user_can_logout_successfully()
    {
        $role = Role::create(['name' => 'client']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $token = $user->createToken('auth_token', ['client'])->plainTextToken;

        $response = $this->postJson("{$this->baseUrl}/logout", [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Logged out successfully',
                 ]);
    }
}
