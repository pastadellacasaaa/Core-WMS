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
    private const AGENT = 'wave-planning';

    /** Item codes and UOMs that exist in The Fifteen's seeded warehouse. */
    private const SAMPLE_ORDERS = [
        ['orderNo' => 'SO-2001', 'clientCode' => 'SFS', 'itemCode' => 'DRY-0146', 'quantity' => '5', 'uom' => 'CTNS', 'expiryDate' => '', 'warehouseLocation' => ''],
        ['orderNo' => 'SO-2001', 'clientCode' => 'SFS', 'itemCode' => 'DRY-0147', 'quantity' => '3', 'uom' => 'CTNS', 'expiryDate' => '', 'warehouseLocation' => ''],
        ['orderNo' => 'SO-2002', 'clientCode' => 'SFS', 'itemCode' => 'DRY-0150', 'quantity' => '20', 'uom' => 'CTNS', 'expiryDate' => '', 'warehouseLocation' => ''],
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view('wave-planning', [
            'orders' => self::SAMPLE_ORDERS,
            'endpoint' => $agentManager->endpoint(self::AGENT),
        ]);
    }

    public function plan(Request $request, AgentManagerClient $agentManager): View
    {
        $orders = array_values(array_filter(
            (array) $request->input('orders', []),
            // The form always posts its blank spare rows; they are not input.
            static fn ($order): bool => is_array($order) && implode('', array_map('strval', $order)) !== '',
        ));

        $request->merge(['orders' => $orders])->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.orderNo' => ['required', 'string', 'max:50'],
            'orders.*.clientCode' => ['required', 'string', 'max:50'],
            'orders.*.itemCode' => ['required', 'string', 'max:50'],
            'orders.*.quantity' => ['required', 'numeric', 'gt:0'],
            'orders.*.uom' => ['required', 'string', 'max:10'],
            'orders.*.expiryDate' => ['nullable', 'date_format:Y-m-d'],
            'orders.*.warehouseLocation' => ['nullable', 'string', 'max:80'],
        ]);

        $startedAt = microtime(true);
        $response = $agentManager->run(self::AGENT, ['orders' => $orders]);
        $elapsed = microtime(true) - $startedAt;

        return view('wave-planning', [
            'orders' => $orders,
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
