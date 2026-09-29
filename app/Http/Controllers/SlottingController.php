<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test harness for the Intelligent Slotting agent: submit items, see the bins
 * the Agent Manager would put them in and why.
 */
class SlottingController extends Controller
{
    private const AGENT = 'intelligent-slotting';

    /** A client, item and UOM the slotting model knows about. */
    private const SAMPLE_ITEMS = [
        [
            'clientCode' => 'SFS',
            'itemCode' => 'CHILLED-0058',
            'quantity' => '5',
            'uom' => 'CTNS',
            'expiryDate' => '',
        ],
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view('slotting', [
            'items' => self::SAMPLE_ITEMS,
            'endpoint' => $agentManager->endpoint(self::AGENT),
        ]);
    }

    public function recommend(Request $request, AgentManagerClient $agentManager): View
    {
        $items = array_values(array_filter(
            (array) $request->input('items', []),
            static fn ($item): bool => is_array($item) && implode('', array_map('strval', $item)) !== '',
        ));

        $validated = $request->merge(['items' => $items])->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.clientCode' => ['required', 'string', 'max:80'],
            'items.*.itemCode' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.uom' => ['required', 'string', 'max:30'],
            'items.*.expiryDate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $startedAt = microtime(true);
        $response = $agentManager->run(self::AGENT, $validated);

        $elapsed = microtime(true) - $startedAt;

        return view('slotting', [
            'items' => $validated['items'],
            'endpoint' => $agentManager->endpoint(self::AGENT),
            'status' => $response->status(),
            'elapsed' => $elapsed,
            'result' => $response->successful() ? $response->json() : null,
            'error' => $response->successful()
                ? null
                : sprintf(
                    '%s (HTTP %d)',
                    $response->json('message') ?? 'Agent Manager request failed.',
                    $response->status(),
                ),
        ]);
    }
}
