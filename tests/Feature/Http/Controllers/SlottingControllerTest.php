<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlottingControllerTest extends TestCase
{
    public function test_sends_an_item_array_and_renders_allocated_and_unallocated_results(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake([
            'http://agent.test/intelligent-slotting' => Http::response([
                'agent' => 'intelligent-slotting',
                'status' => 'Partial',
                'processedAt' => '2026-09-10T12:00:00+08:00',
                'slottingLocations' => [[
                    'rank' => 1,
                    'itemCode' => 'CHILLED-0058',
                    'location' => 'ACHL1-A-01-01',
                    'quantity' => 5,
                    'uom' => 'CTNS',
                    'reasons' => ['Bin has enough capacity.'],
                ]],
                'unallocated' => [[
                    'clientCode' => 'SFS',
                    'itemCode' => 'MISSING-1',
                    'quantity' => 2,
                    'uom' => 'CTNS',
                    'reason' => 'No slotting recommendation was found for this item.',
                ]],
            ]),
        ]);

        $response = $this->post('/slotting', [
            'items' => [
                ['clientCode' => 'SFS', 'itemCode' => 'CHILLED-0058', 'quantity' => 5, 'uom' => 'CTNS', 'expiryDate' => '2026-12-31'],
                ['clientCode' => 'SFS', 'itemCode' => 'MISSING-1', 'quantity' => 2, 'uom' => 'CTNS', 'expiryDate' => null],
            ],
        ]);

        $response
            ->assertSee('ACHL1-A-01-01')
            ->assertSee('MISSING-1')
            ->assertSee('No slotting recommendation was found for this item.');
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://agent.test/intelligent-slotting'
            && $request->data()['items'][0]['expiryDate'] === '2026-12-31'
            && count($request->data()['items']) === 2);
    }
}
