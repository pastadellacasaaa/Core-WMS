<?php
namespace Tests\Feature;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class InventorySourceFormsTest extends TestCase
{
    public function test_auto_routing_sends_existing_json_without_source_selection(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => [], 'agent' => 'demand-forecasting'])]);
        $body = ['clientCode' => 'SFS', 'itemCode' => 'DRY-0146', 'forecastDays' => 14];
        $this->post('/', ['payload' => json_encode($body)])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->data() === $body);
    }
    public function test_forecasting_sends_existing_fields_without_source_selection(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['message' => 'test failure'], 502)]);
        $this->post('/forecasting', ['clientCode' => 'SFS', 'itemCode' => 'DRY-0146', 'forecastDays' => 14])->assertOk();
        Http::assertSent(fn ($r) => $r->url() === 'http://agent.test/demand-forecasting' && ! array_key_exists('inventorySource', $r->data()));
    }
}