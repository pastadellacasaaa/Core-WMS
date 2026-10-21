<?php

namespace App\Http\Controllers;

use App\Services\AgentManagerClient;
use App\Services\InventorySnapshot;
use Illuminate\Http\JsonResponse;
use Throwable;

class InventoryCheckController extends Controller
{
    public function __invoke(AgentManagerClient $client, InventorySnapshot $inventory): JsonResponse
    {
        try {
            $response = $client->contracts();
            $source = $response->json('inventory.source');
            if (! $response->successful() || $source !== 'api') {
                return response()->json(['ready' => false, 'message' => 'Agent Manager is not confirmed to use Core WMS API inventory.'], 503);
            }
            $page = $inventory->page([]);
            return response()->json([
                'ready' => true,
                'message' => 'Core WMS Excel inventory is enabled. Local iWMS inventory fallback is disabled.',
                'workbook' => basename(config('inventory.snapshot_path')),
                'sourceRows' => $inventory->importReport['sourceRows'],
                'recordsOnFirstPage' => count($page['data']),
                'hasMorePages' => $page['nextCursor'] !== null,
            ])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['ready' => false, 'message' => 'Inventory check failed. Check the workbook and Agent Manager connection.'], 503);
        }
    }
}
