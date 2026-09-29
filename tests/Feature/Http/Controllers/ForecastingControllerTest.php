<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastingControllerTest extends TestCase
{
    public function test_renders_reorder_actions_from_the_agent_response(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake([
            'http://agent.test/demand-forecasting' => Http::response([
                'agent' => 'demand-forecasting',
                'status' => 'Success',
                'processedAt' => '2026-09-10T12:00:00+08:00',
                'forecastFromDate' => '2026-09-10',
                'forecastToDate' => '2026-10-01',
                'forecastPoints' => [],
                'reorderAlert' => [
                    'severity' => 'High',
                    'reason' => 'Projected stockout.',
                ],
                'reorderActions' => [[
                    'quantity' => 12,
                    'uom' => 'CTNS',
                    'date' => '2026-09-17',
                    'reasons' => ['Order before the projected stockout.'],
                ]],
            ]),
        ]);

        $response = $this->post('/forecasting', [
            'clientCode' => 'SFS',
            'itemCode' => 'FROZEN-0050',
            'forecastDays' => '21',
        ]);

        $response
            ->assertSee('12')
            ->assertSee('2026-09-17')
            ->assertSee('Order before the projected stockout.');
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://agent.test/demand-forecasting'
            && $request->data()['forecastDays'] === 21);
    }
}
