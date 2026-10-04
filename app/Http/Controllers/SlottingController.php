<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\AgentManagerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simulates a client WMS requesting an Intelligent Slotting recommendation.
 */
class SlottingController extends Controller
{
    private const AGENT = 'intelligent-slotting';

    public function show(AgentManagerClient $agentManager): View
    {
        $products = $this->products();

        return view('slotting', [
            'products' => $products,
            'items' => $this->defaultItems($products),
            'endpoint' => $agentManager->endpoint(self::AGENT),
            'contract' => $this->contract($agentManager),
        ]);
    }

    public function recommend(
        Request $request,
        AgentManagerClient $agentManager
    ): View {
        $products = $this->products();

        $items = array_values(array_filter(
            (array) $request->input('items', []),
            static fn ($item): bool =>
                is_array($item)
                && implode('', array_map('strval', $item)) !== '',
        ));

        $validated = $request
            ->merge(['items' => $items])
            ->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*' => ['required', 'array'],
                'items.*.clientCode' => ['required', 'string', 'min:1', 'max:80'],
                'items.*.itemCode' => ['required', 'string', 'min:1', 'max:80'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.uom' => ['required', 'string', 'min:1', 'max:30'],
                'items.*.expiryDate' => [
                    'sometimes',
                    'nullable',
                    'date_format:Y-m-d',
                ],
            ]);

        $startedAt = microtime(true);

        $response = $agentManager->run(
            self::AGENT,
            $validated
        );

        $elapsed = microtime(true) - $startedAt;

        return view('slotting', [
            'products' => $products,
            'items' => $validated['items'],
            'endpoint' => $agentManager->endpoint(self::AGENT),
            'contract' => $this->contract($agentManager),
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
                    $response->status(),
                ),
        ]);
    }

    private function products(): array
    {
        return Product::query()
            ->orderBy('client_code')
            ->orderBy('sku')
            ->orderBy('uom')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'clientCode' => $product->client_code,
                'itemCode' => $product->sku,
                'uom' => $product->uom,
                'category' => $product->category,
                'unitPerPack' => $product->unit_per_pack,
                'weightKg' => $product->weight_kg,
            ])
            ->all();
    }

    private function defaultItems(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $product = $products[0];

        return [
            [
                'clientCode' => $product['clientCode'],
                'itemCode' => $product['itemCode'],
                'quantity' => '1',
                'uom' => $product['uom'],
                'expiryDate' => '',
            ],
        ];
    }

    private function contract(AgentManagerClient $agentManager): array
    {
        $response = $agentManager->contracts();

        if (! $response->successful()) {
            return [];
        }

        return $response->json('data.'.self::AGENT) ?? [];
    }
}