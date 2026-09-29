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
            ->assertSee('Auto-route')
            ->assertSee('FROZEN-1361')
            ->assertSee('2029-02-01')
            ->assertSee('EFRZ2-A-13-04')
            ->assertDontSee('class="tabs"', false);
    }
}
