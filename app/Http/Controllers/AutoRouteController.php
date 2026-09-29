<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test harness for the agent-less endpoint: post a payload with no agent named
 * and watch the Agent Manager work out which agent the fields belong to.
 */
class AutoRouteController extends Controller
{
    /**
     * Payloads that exercise each routing outcome. Deliberately no agent name
     * anywhere - the field set is the only thing that decides.
     */
    private const SAMPLES = [
        'Slotting fields' => [
            'items' => [
                [
                    'clientCode' => 'SFS',
                    'itemCode' => 'CHILLED-0058',
                    'quantity' => 5,
                    'uom' => 'CTNS',
                ],
            ],
        ],
        'Forecasting fields' => [
            'clientCode' => 'SFS',
            'itemCode' => 'FROZEN-0050',
            'forecastDays' => 14,
        ],
        'Wave planning fields' => [
            'orders' => [
                ['orderNo' => 'SO-3001', 'clientCode' => 'SFS', 'itemCode' => 'DRY-0146', 'quantity' => 10, 'uom' => 'CTNS', 'expiryDate' => '2028-02-23'],
                ['orderNo' => 'SO-3002', 'clientCode' => 'SFS', 'itemCode' => 'DRY-0147', 'quantity' => 4, 'uom' => 'CTNS', 'warehouseLocation' => 'CDRY1-C-03-02'],
            ],
        ],
        'Too few fields to route' => [
            'clientCode' => 'SFS',
            'itemCode' => 'CHILLED-0058',
        ],
        'A field no agent knows' => [
            'clientCode' => 'SFS',
            'itemCode' => 'CHILLED-0058',
            'quantity' => 5,
            'uom' => 'CTNS',
            'forecastDays' => 30,
        ],
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view('auto-route', $this->page($agentManager, $this->pretty(array_values(self::SAMPLES)[0])));
    }

    public function send(Request $request, AgentManagerClient $agentManager): View
    {
        $body = (string) $request->input('payload', '');
        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return view('auto-route', $this->page($agentManager, $body) + [
                'error' => 'That is not valid JSON: '.json_last_error_msg(),
            ]);
        }

        $startedAt = microtime(true);
        $response = $agentManager->dispatch($payload);
        $elapsed = microtime(true) - $startedAt;
        $result = $response->json();

        return view('auto-route', $this->page($agentManager, $this->pretty($payload)) + [
            'status' => $response->status(),
            'elapsed' => $elapsed,
            // The Agent Manager echoes the agent it chose; on a refusal it says why.
            'agent' => is_array($result) ? ($result['agent'] ?? null) : null,
            'message' => is_array($result) ? ($result['message'] ?? null) : null,
            'body' => $this->pretty($this->shorten($result)),
            'maps' => collect($result['pickingLists'] ?? [])->pluck('maps')->flatten(1)->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(AgentManagerClient $agentManager, string $payload): array
    {
        $contracts = $agentManager->contracts();

        return [
            'payload' => $payload,
            'endpoint' => $agentManager->endpoint(),
            'samples' => array_map(fn (array $sample): string => $this->pretty($sample), self::SAMPLES),
            'contracts' => $contracts->successful() ? ($contracts->json('data') ?? []) : [],
        ];
    }

    private function pretty(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The wave planning reply carries a whole SVG map per wave, which would bury
     * the rest of the response. Long strings are clipped for display only.
     */
    private function shorten(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->shorten($item), $value);
        }

        if (is_string($value) && mb_strlen($value) > 160) {
            return mb_substr($value, 0, 160).'... ['.mb_strlen($value).' characters, clipped]';
        }

        return $value;
    }
}
