<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoRouteController extends Controller
{
    private const SAMPLES = [
        'Intelligent Slotting' => [
            'clientCode' => 'SCS',
            'item' => [
                'itemCode' => 'FROZEN-0027',
                'quantity' => 5,
                'uom' => 'CTN',
                'expiryDate' => '2028-06-30',
            ],
        ],

        'Wave Planning' => [
            'pickingListId' => 'PL001',
            'warehouseZone' => 'EFRZ1',
            'orders' => [
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
            ],
        ],

        'Demand Forecasting' => [
            'clientCode' => 'SFS',
            'itemCode' => 'DRY-0146',
            'forecastDays' => 14,
        ],

        'Missing Required Fields' => [
            'clientCode' => 'SCS',
            'itemCode' => 'FROZEN-0027',
        ],

        'Unsupported Field Combination' => [
            'clientCode' => 'SCS',
            'itemCode' => 'FROZEN-0027',
            'quantity' => 5,
            'uom' => 'CTN',
            'forecastDays' => 30,
        ],
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view(
            'auto-route',
            $this->page(
                $agentManager,
                $this->pretty(array_values(self::SAMPLES)[0])
            )
        );
    }

    public function send(
        Request $request,
        AgentManagerClient $agentManager
    ): View {
        $body = (string) $request->input('payload', '');
        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return view(
                'auto-route',
                $this->page($agentManager, $body) + [
                    'error' => 'That is not valid JSON: '
                        .json_last_error_msg(),
                ]
            );
        }

        $startedAt = microtime(true);

        $response = $agentManager->dispatch($payload);

        $elapsed = microtime(true) - $startedAt;
        $result = $response->json();

        return view(
            'auto-route',
            $this->page(
                $agentManager,
                $this->pretty($payload)
            ) + [
                'status' => $response->status(),
                'elapsed' => $elapsed,
                'agent' => is_array($result)
                    ? ($result['agent'] ?? null)
                    : null,
                'message' => is_array($result)
                    ? ($result['message'] ?? null)
                    : null,
                'body' => $this->pretty(
                    $this->shorten($result)
                ),
                'maps' => collect(
                    $result['pickingLists'] ?? []
                )
                    ->pluck('maps')
                    ->flatten(1)
                    ->all(),
            ]
        );
    }

    private function page(
        AgentManagerClient $agentManager,
        string $payload
    ): array {
        $contracts = $agentManager->contracts();

        return [
            'payload' => $payload,
            'endpoint' => $agentManager->endpoint(),
            'samples' => array_map(
                fn (array $sample): string =>
                    $this->pretty($sample),
                self::SAMPLES
            ),
            'contracts' => $contracts->successful()
                ? ($contracts->json('data') ?? [])
                : [],
        ];
    }

    private function pretty(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    private function shorten(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed =>
                    $this->shorten($item),
                $value
            );
        }

        if (is_string($value) && mb_strlen($value) > 160) {
            return mb_substr($value, 0, 160)
                .'... ['
                .mb_strlen($value)
                .' characters, clipped]';
        }

        return $value;
    }
}