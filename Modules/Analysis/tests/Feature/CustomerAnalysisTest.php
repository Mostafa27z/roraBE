<?php

namespace Modules\Analysis\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Modules\Auth\Models\Role;
use Modules\Orders\Models\Order;

class CustomerAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $clientRole = Role::factory()->create(['name' => 'client']);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);

        // Authenticate as admin
        Sanctum::actingAs($this->admin, ['*']);
    }

    /** @test */
    public function admin_can_view_customer_analysis()
    {
        $clients = User::factory()->count(3)->create();
        $clientRole = Role::where('name', 'client')->first();
        foreach ($clients as $client) {
            $client->roles()->attach($clientRole->id);
        }

        // Create orders
        Order::factory()->create(['user_id' => $clients[0]->id, 'total' => 100]);
        Order::factory()->create(['user_id' => $clients[0]->id, 'total' => 50]);
        Order::factory()->create(['user_id' => $clients[1]->id, 'total' => 200]);

        $response = $this->getJson('/api/analysis/customers');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'overview' => [
                         'total_customers',
                         'repeat_customers',
                         'average_orders_per_customer',
                     ],
                     'top_customers',
                 ]);
    }

    /** @test */
    public function client_cannot_access_customer_analysis()
    {
        $clientRole = Role::where('name', 'client')->first() ?? Role::factory()->create(['name' => 'client']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole->id);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/analysis/customers');

        $response->assertStatus(403);
    }
}
