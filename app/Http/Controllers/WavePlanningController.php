<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test harness for the Wave Planning & Picking Route Optimisation agent:
 * submit a set of order lines, see the picking list and the 2D location map the
 * Agent Manager sends back.
 */
class WavePlanningController extends Controller
{
    private const AGENT = 'wave-planning-and-route-optimization';
    private const SAMPLE_PICKING_LIST_ID = 'PL001';
    private const SAMPLE_ORDERS = [
        [
            'orderNo' => 'ORD001',
            'clientCode' => 'SCS',
            'items' => [
                [
                    'itemCode' => 'FROZEN-1444',
                    'quantity' => 1,
                    'uom' => 'CTN',
                ],
            ],
        ],
        [
            'orderNo' => 'ORD002',
            'clientCode' => 'SCS',
            'items' => [
                [
                    'itemCode' => 'FROZEN-1045',
                    'quantity' => 1,
                    'uom' => 'CTN',
                ],
            ],
        ],
        [
            'orderNo' => 'ORD003',
            'clientCode' => 'SCS',
            'items' => [
                [
                    'itemCode' => 'FROZEN-1551',
                    'quantity' => 1,
                    'uom' => 'CTN',
                ],
            ],
        ],
        [
            'orderNo' => 'ORD005',
            'clientCode' => 'SFS',
            'items' => [
                [
                    'itemCode' => 'FROZEN-0338',
                    'quantity' => 1,
                    'uom' => 'CTN',
                ],
            ],
        ],
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view('wave-planning', [
            'pickingListId' => self::SAMPLE_PICKING_LIST_ID,
            'warehouseZone' => 'EFRZ1',
            'orders' => self::SAMPLE_ORDERS,
            'endpoint' => $agentManager->endpoint(self::AGENT),
        ]);
    }

    public function plan(
        Request $request,
        AgentManagerClient $agentManager
    ): View {
        $validated = $request->validate([
            'pickingListId' => [
                'required', 'string', 'max:50',
            ],
            'warehouseZone' => ['required', 'string', 'max:50'],
            'orders' => [
                'required', 'array', 'min:1',
            ],
            'orders.*.orderNo' => [
                'required', 'string', 'max:50',
            ],
            'orders.*.clientCode' => [
                'required', 'string', 'max:50',
            ],
            'orders.*.items' => [
                'required', 'array', 'min:1',
            ],
            'orders.*.items.*.itemCode' => [
                'required', 'string', 'max:50',
            ],
            'orders.*.items.*.quantity' => [
                'required', 'numeric', 'gt:0',
            ],
            'orders.*.items.*.uom' => [
                'required', 'string', 'max:10',
            ],
            'orders.*.items.*.specificExpiryDate' => [
                'nullable', 'date_format:Y-m-d',
            ],
            'orders.*.items.*.specificLocation' => [
                'nullable', 'string', 'max:80',
            ],
        ]);

        $orders = array_map(
            static function (array $order): array {
                $order['items'] = array_map(
                    static function (array $item): array {
                        $item['quantity'] = (float) $item['quantity'];

                        foreach ([
                            'specificExpiryDate',
                            'specificLocation',
                        ] as $field) {
                            if (
                                ! isset($item[$field])
                                || $item[$field] === ''
                            ) {
                                unset($item[$field]);
                            }
                        }

                        return $item;
                    },
                    array_values($order['items'])
                );

                return $order;
            },
            array_values($validated['orders'])
        );

        $payload = [
            'pickingListId' => $validated['pickingListId'],
            'warehouseZone' => $validated['warehouseZone'],
            'orders' => $orders,
        ];

        $startedAt = microtime(true);

        $response = $agentManager->run(
            self::AGENT,
            $payload
        );

        $elapsed = microtime(true) - $startedAt;

        return view('wave-planning', [
            'pickingListId' => $validated['pickingListId'],
            'warehouseZone' => $validated['warehouseZone'],
            'orders' => $orders,
            'endpoint' => $agentManager->endpoint(self::AGENT),
            'status' => $response->status(),
            'elapsed' => $elapsed,
            'result' => $response->successful()
                ? $response->json()
                : null,
            'error' => $response->successful()
                ? null
                : sprintf(
                    '%s (HTTP %d)',
                    $response->json('message')
                        ?? 'Agent Manager request failed.',
                    $response->status()
                ),
        ]);
    }
}