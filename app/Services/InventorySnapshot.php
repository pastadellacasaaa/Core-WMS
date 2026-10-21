<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class InventorySnapshot
{
    public array $importReport = [];

    public function page(array $query): array
    {
        $path = (string) config('inventory.snapshot_path');
        abort_unless(is_file($path) && is_readable($path), 503, 'WMS inventory snapshot is unavailable.');
        $contents = file_get_contents($path);
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') {
            try { $snapshot = app(ExcelInventorySnapshot::class)->read($path, $contents); }
            catch (\RuntimeException $exception) { abort(503, $exception->getMessage()); }
        } else {
            try { $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { abort(503, 'WMS inventory snapshot is invalid JSON.'); }
        }
        $this->importReport = $snapshot['_importReport'] ?? ['source' => 'json', 'sourceRows' => count($snapshot['data'] ?? []), 'skippedRows' => 0];
        $validator = Validator::make(is_array($snapshot) ? $snapshot : [], [
            'asOf' => ['required', 'date'], 'data' => ['present', 'array', 'list'],
        ]);
        abort_if($validator->fails(), 503, 'WMS inventory snapshot does not match the inventory contract.');
        $rowRules = [
            'batchId' => ['required', 'string'], 'clientCode' => ['required', 'string'],
            'itemCode' => ['required', 'string'], 'warehouseCode' => ['required', 'string'],
            'locationCode' => ['required', 'string'], 'uom' => ['required', 'string'],
            'palletId' => ['required', 'string'],
            'availableQuantity' => ['required', 'numeric', 'min:0'],
            'onHandQuantity' => ['required', 'numeric', 'min:0'],
            'expiryDate' => ['nullable', 'date_format:Y-m-d'],
            'receivedDate' => ['nullable', 'date_format:Y-m-d'],
        ];
        abort_unless(preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $snapshot['asOf']), 503, 'Inventory timestamp must include a timezone.');
        $seen = [];
        foreach ($snapshot['data'] as &$row) {
            abort_unless(is_array($row), 503, 'Inventory row must be an object.');
            abort_if(array_diff(array_keys($row), array_keys($rowRules)) !== [], 503, 'Inventory row has unknown fields.');
            abort_if(Validator::make($row, $rowRules)->fails(), 503, 'Inventory row does not match the contract.');
            foreach (['batchId', 'clientCode', 'itemCode', 'warehouseCode', 'locationCode', 'uom', 'palletId'] as $field) {
                $row[$field] = trim($row[$field]);
                abort_if($row[$field] === '', 503, 'Inventory identifiers must not be blank.');
            }
            foreach (['availableQuantity', 'onHandQuantity'] as $field) {
                $row[$field] = (float) $row[$field];
                abort_unless(is_finite($row[$field]), 503, 'Inventory quantities must be finite.');
            }
            abort_if($row['availableQuantity'] > $row['onHandQuantity'], 503, 'Available inventory exceeds physical stock.');
            $key = json_encode([
                strtoupper($row['clientCode']), strtoupper($row['itemCode']), strtoupper($row['warehouseCode']),
                strtoupper($row['locationCode']), $row['batchId'], strtoupper($row['uom']),
                $row['expiryDate'] ?? null, $row['palletId'],
            ]);
            abort_if(isset($seen[$key]), 503, 'Inventory snapshot contains duplicate stock rows.');
            $seen[$key] = true;
        }
        unset($row);
        $filters = [
            'clientCode' => strtoupper(trim($query['clientCode'] ?? '')),
            'itemCode' => strtoupper(trim($query['itemCode'] ?? '')),
        ];
        $fingerprint = hash('sha256', $contents.($snapshot['_mappingFingerprint'] ?? '').json_encode($filters));
        $offset = 0;
        if (isset($query['cursor'])) {
            $cursor = json_decode(base64_decode(strtr($query['cursor'], '-_', '+/'), true) ?: '', true);
            abort_unless(is_array($cursor) && isset($cursor['snapshot'], $cursor['offset']) && is_int($cursor['offset']) && $cursor['offset'] >= 0, 422, 'Invalid inventory cursor.');
            abort_unless(hash_equals($fingerprint, (string) $cursor['snapshot']), 409, 'Inventory or filters changed; restart pagination.');
            $offset = $cursor['offset'];
        }
        $rows = array_values(array_filter($snapshot['data'], static function (array $row) use ($filters): bool {
            foreach ($filters as $field => $value) {
                if ($value !== '' && strtoupper($row[$field]) !== $value) {
                    return false;
                }
            }
            return true;
        }));
        abort_if($offset > count($rows), 422, 'Inventory cursor is out of range.');
        $size = max(1, (int) config('inventory.page_size', 500));
        $nextOffset = $offset + $size;
        $next = $nextOffset < count($rows)
            ? rtrim(strtr(base64_encode(json_encode(['snapshot' => $fingerprint, 'offset' => $nextOffset])), '+/', '-_'), '=')
            : null;
        return ['asOf' => Carbon::parse($snapshot['asOf'])->toIso8601String(), 'data' => array_slice($rows, $offset, $size), 'nextCursor' => $next];
    }
}