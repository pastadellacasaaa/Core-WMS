<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlottingControllerTest extends TestCase
{
    public function test_sends_request_level_client_and_item_array_and_renders_recommendations(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        config([
            'services.agent_manager.base_url' => 'http://agent.test',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'http://agent.test/intelligent-slotting' => Http::response([
                'actionType' => 'slotting_recommendation',
                'priority' => 'normal',
                'reason' => 'Put-away recommendations were generated successfully.',
                'moduleOutputs' => [
                    [
                        'query' => [
                            'sku_id' => 'CHILLED-0058',
                            'client_id' => 'SFS',
                            'uom' => 'CTNS',
                            'quantity' => 5.0,
                            'quantity_kg' => 30.0,
                            'expiry_date' => '2026-12-31',
                        ],
                        'recommendation' => [
                            'location_code' => 'ACHL1-A-01-01',
                            'putaway_score' => 90.3,
                        ],
                        'short_reason' => 'Baseline model ranked this bin after warehouse rules passed',
                        'status' => 'recommended',
                    ],
                    [
                        'query' => [
                            'sku_id' => 'MISSING-1',
                            'client_id' => 'SFS',
                            'uom' => 'CTNS',
                            'quantity' => 2.0,
                            'quantity_kg' => null,
                            'expiry_date' => null,
                        ],
                        'recommendation' => null,
                        'short_reason' => 'No slotting recommendation was found for this item.',
                        'status' => 'unallocated',
                    ],
                ],
            ]),
        ]);

        $response = $this->post('/slotting', [
            'clientCode' => 'SFS',
            'items' => [
                [
                    'itemCode' => 'CHILLED-0058',
                    'quantity' => 5,
                    'uom' => 'CTNS',
                    'expiryDate' => '2026-12-31',
                ],
                [
                    'itemCode' => 'MISSING-1',
                    'quantity' => 2,
                    'uom' => 'CTNS',
                    'expiryDate' => null,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('CHILLED-0058')
            ->assertSee('ACHL1-A-01-01')
            ->assertSee('MISSING-1')
            ->assertSee('No slotting recommendation was found for this item.');

        Http::assertSent(
            fn (ClientRequest $request): bool =>
                $request->url() === 'http://agent.test/intelligent-slotting'
                && $request->data()['clientCode'] === 'SFS'
                && !array_key_exists(
                    'clientCode',
                    $request->data()['items'][0]
                )
                && $request->data()['items'][0]['itemCode'] === 'CHILLED-0058'
                && $request->data()['items'][0]['expiryDate'] === '2026-12-31'
                && count($request->data()['items']) === 2
        );
    }
}