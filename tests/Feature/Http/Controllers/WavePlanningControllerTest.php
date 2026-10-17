<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WavePlanningControllerTest extends TestCase
{
    public function test_sends_picking_list_id_and_optional_constraints(): void
    {
        $this->withoutExceptionHandling();
        $this->withoutMiddleware(PreventRequestForgery::class);

        config([
            'services.agent_manager.base_url' => 'http://agent.test',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'http://agent.test/wave-planning' => Http::response([
                'agent' => 'wave-planning-and-route-optimization',
                'status' => 'Success',
                'processedAt' => '2026-09-10T12:00:00+08:00',
                'pickingLists' => [
                    [
                        'warehouseZone' => 'AFRZ1',
                        'pickingList' => [
                            [
                                'travelSequence' => 1,
                                'location' => 'AFRZ1-A-02-03',
                                'orderNo' => 'SO-2001',
                                'clientCode' => 'SFS',
                                'itemCode' => 'FROZEN-0027',
                                'quantity' => 5,
                                'uom' => 'CTNS',
                                'reasons' => [
                                    'Requested expiry and location matched.',
                                ],
                            ],
                        ],
                        'maps' => [
                            [
                                'waveCode' => 'AFRZ1-260910-0001',
                                'widthPx' => 160,
                                'heightPx' => 328,
                                'svg' => '<svg><rect data-location="AFRZ1-A-02-03"/></svg>',
                                'locations' => [
                                    [
                                        'locationCode' => 'AFRZ1-A-02-03',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'unallocated' => [],
                'warnings' => [],
            ]),
        ]);

        $response = $this->post('/wave-planning', [
            'pickingListId' => 'PL001',
            'warehouseZone' => 'AFRZ1',
            'orders' => [
                [
                    'orderNo' => 'SO-2001',
                    'clientCode' => 'SFS',
                    'items' => [
                        [
                            'itemCode' => 'FROZEN-0027',
                            'quantity' => '5',
                            'uom' => 'CTNS',
                            'specificExpiryDate' => '2026-12-31',
                            'specificLocation' => 'AFRZ1-A-02-03',
                        ],
                        [
                            'itemCode' => 'DRY-0211',
                            'quantity' => '10',
                            'uom' => 'CTN',
                            'specificExpiryDate' => '',
                            'specificLocation' => '',
                        ],
                    ],
                ],
                [
                    'orderNo' => 'SO-2002',
                    'clientCode' => 'SFS',
                    'items' => [
                        [
                            'itemCode' => 'DRY-0212',
                            'quantity' => '3',
                            'uom' => 'CTN',
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee(
                'Completed location map for wave AFRZ1-260910-0001'
            )
            ->assertSee('name="pickingListId"', false)
            ->assertDontSee('name="orders[0][pickingListId]"', false);

        Http::assertSent(
            fn (ClientRequest $request): bool =>
                $request->url() === 'http://agent.test/wave-planning'
                && $request->data()['pickingListId'] === 'PL001'
                && $request->data()['warehouseZone'] === 'AFRZ1'
                && count($request->data()['orders']) === 2
                && ! array_key_exists(
                    'pickingListId',
                    $request->data()['orders'][0]
                )
                && $request->data()['orders'][0]['items'][0]['specificExpiryDate'] === '2026-12-31'
                && $request->data()['orders'][0]['items'][0]['specificLocation'] === 'AFRZ1-A-02-03'
                && is_numeric(
                    $request->data()['orders'][0]['items'][1]['quantity']
                )
                && $request->data()['orders'][0]['items'][1]['quantity'] == 10
                && ! array_key_exists(
                    'specificExpiryDate',
                    $request->data()['orders'][0]['items'][1]
                )
                && ! array_key_exists(
                    'specificLocation',
                    $request->data()['orders'][0]['items'][1]
                )
        );
    }

    public function test_requires_picking_list_id_and_valid_items(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        Http::preventStrayRequests();

        $this->from('/wave-planning')->post('/wave-planning', [
            'orders' => [
                [
                    'orderNo' => 'ORD001',
                    'clientCode' => 'SFS',
                    'items' => [
                        [
                            'itemCode' => 'DRY-0211',
                            'quantity' => 0,
                            'uom' => 'CTN',
                            'specificExpiryDate' => 'invalid',
                        ],
                    ],
                ],
            ],
        ])
            ->assertRedirect('/wave-planning')
            ->assertSessionHasErrors([
                'pickingListId',
                'warehouseZone',
                'orders.0.items.0.quantity',
                'orders.0.items.0.specificExpiryDate',
            ]);

        Http::assertNothingSent();
    }

    public function test_requires_client_code_for_each_order(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        Http::preventStrayRequests();

        $response = $this->from('/wave-planning')->post(
            '/wave-planning',
            [
                'pickingListId' => 'PL001',
                'warehouseZone' => 'AFRZ1',
                'orders' => [
                    [
                        'orderNo' => 'SO-2001',
                        'items' => [
                            [
                                'itemCode' => 'FROZEN-0027',
                                'quantity' => 5,
                                'uom' => 'CTNS',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $response
            ->assertRedirect('/wave-planning')
            ->assertSessionHasErrors('orders.0.clientCode');

        Http::assertNothingSent();
    }
}