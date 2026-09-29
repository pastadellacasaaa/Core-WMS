<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test harness for the Demand Forecasting agent: submit one item and a horizon,
 * see the forecast points the Agent Manager sends back.
 */
class ForecastingController extends Controller
{
    private const AGENT = 'demand-forecasting';

    private const SAMPLE_QUERY = [
        'clientCode' => 'SFS',
        'itemCode' => 'FROZEN-0050',
        'forecastDays' => '21',
    ];

    public function show(AgentManagerClient $agentManager): View
    {
        return view('forecasting', [
            'query' => self::SAMPLE_QUERY,
            'endpoint' => $agentManager->endpoint(self::AGENT),
        ]);
    }

    public function forecast(Request $request, AgentManagerClient $agentManager): View
    {
        $validated = $request->validate([
            'clientCode' => ['required', 'string', 'max:80'],
            'itemCode' => ['required', 'string', 'max:80'],
            'forecastDays' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $startedAt = microtime(true);
        $response = $agentManager->run(self::AGENT, [
            ...$validated,
            'forecastDays' => (int) $validated['forecastDays'],
        ]);

        $elapsed = microtime(true) - $startedAt;

        return view('forecasting', [
            'query' => $validated,
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
