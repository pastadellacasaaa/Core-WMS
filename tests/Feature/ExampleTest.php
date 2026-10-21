<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::fake([
            'http://agent.test' => Http::response(['data' => []]),
        ]);

        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('Automatic Routing Request')
            ->assertSee('FROZEN-1444')
            ->assertSee('Inventory simulation')
            ->assertSee('Check inventory connection')
            ->assertDontSee('name="inventorySource"', false)
            ->assertDontSee('class="tabs"', false);
    }
}
