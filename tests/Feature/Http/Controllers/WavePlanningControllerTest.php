<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WavePlanningControllerTest extends TestCase
{
    public function test_sends_optional_allocation_constraints_and_renders_the_location_map(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake([
            'http://agent.test/wave-planning-and-route-optimization' => Http::response([
                'agent' => 'wave-planning-and-route-optimization',
                'status' => 'Success',
                'processedAt' => '2026-09-10T12:00:00+08:00',
                'pickingLists' => [[
                    'warehouseZone' => 'AFRZ1',
                    'pickingList' => [[
                        'travelSequence' => 1,
                        'location' => 'AFRZ1-A-02-03',
                        'orderNo' => 'SO-2001',
                        'clientCode' => 'SFS',
                        'itemCode' => 'FROZEN-0027',
                        'quantity' => 5,
                        'uom' => 'CTNS',
                        'reasons' => ['Requested expiry and location matched.'],
                    ]],
                    'maps' => [[
                        'waveCode' => 'AFRZ1-260910-0001',
                        'widthPx' => 160,
                        'heightPx' => 328,
                        'svg' => '<svg><rect data-location="AFRZ1-A-02-03"/></svg>',
                        'locations' => [['locationCode' => 'AFRZ1-A-02-03']],
                    ]],
                ]],
                'unallocated' => [],
                'warnings' => [],
            ]),
        ]);

        $response = $this->post('/wave-planning', [
            'orders' => [[
                'orderNo' => 'SO-2001',
                'clientCode' => 'SFS',
                'itemCode' => 'FROZEN-0027',
                'quantity' => 5,
                'uom' => 'CTNS',
                'expiryDate' => '2026-12-31',
                'warehouseLocation' => 'AFRZ1-A-02-03',
            ]],
        ]);

        $response
            ->assertSee('Completed location map for wave AFRZ1-260910-0001')
            ->assertSee('1 locations');
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://agent.test/wave-planning-and-route-optimization'
            && $request->data()['orders'][0]['expiryDate'] === '2026-12-31'
            && $request->data()['orders'][0]['warehouseLocation'] === 'AFRZ1-A-02-03');
    }

    public function test_requires_a_client_code_for_each_order(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Http::preventStrayRequests();

        $response = $this->from('/wave-planning')->post('/wave-planning', [
            'orders' => [[
                'orderNo' => 'SO-2001',
                'itemCode' => 'FROZEN-0027',
                'quantity' => 5,
                'uom' => 'CTNS',
            ]],
        ]);

        $response
            ->assertRedirect('/wave-planning')
            ->assertSessionHasErrors('orders.0.clientCode');
        Http::assertNothingSent();
    }
}
