<?php

namespace App\Http\Controllers;

use App\Services\InventorySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __invoke(Request $request, InventorySnapshot $inventory): JsonResponse
    {
        $token = (string) config('inventory.token', '');
        abort_if($token !== '' && ! hash_equals($token, (string) $request->bearerToken()), 401, 'Invalid inventory API token.');
        $query = $request->validate([
            'clientCode' => ['sometimes', 'string', 'min:1', 'max:80'],
            'itemCode' => ['sometimes', 'string', 'min:1', 'max:80'],
            'cursor' => ['sometimes', 'string', 'min:1', 'max:1000'],
        ]);
        $page = $inventory->page($query);
        return response()->json($page)->header('Cache-Control', 'no-store')
            ->header('X-WMS-Inventory-Source', $inventory->importReport['source'])
            ->header('X-WMS-Inventory-Source-Rows', (string) $inventory->importReport['sourceRows'])
            ->header('X-WMS-Inventory-Skipped-Rows', (string) $inventory->importReport['skippedRows']);
    }
}