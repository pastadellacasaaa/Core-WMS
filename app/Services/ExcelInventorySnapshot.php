<?php

namespace App\Services;

use App\Services\Spreadsheet\XlsxReader;
use Illuminate\Support\Carbon;
use RuntimeException;

class ExcelInventorySnapshot
{
    public function __construct(private readonly XlsxReader $reader) {}

    public function read(string $path, string $contents): array
    {
        $sheet = (string) config('inventory.worksheet', 'INVENTORY');
        $rows = $this->reader->rows($path, $sheet);
        if ($rows === []) { throw new RuntimeException('The inventory worksheet has no header.'); }
        $headers = [];
        foreach (array_shift($rows) as $index => $value) {
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));
            if ($key !== '') { $headers[$key] = $index; }
        }
        $columns = [];
        $aliases = [
            'batchId' => ['batchid'], 'itemCode' => ['skuid', 'itemcode', 'sku'],
            'clientCode' => ['clientid', 'clientcode'], 'uom' => ['uom'],
            'warehouseCode' => ['warehouseid', 'warehousecode'],
            'locationCode' => ['locationcode', 'location'], 'palletId' => ['palletid'],
            'availableQuantity' => ['availablequantity', 'availableqty'],
            'onHandQuantity' => ['onhandquantity', 'onhandqty'],
            'expiryDate' => ['expiry', 'expirydate'], 'receivedDate' => ['receiveddate'], 'status' => ['status'],
        ];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) { if (isset($headers[$name])) { $columns[$field] = $headers[$name]; break; } }
        }
        foreach (['batchId', 'itemCode', 'clientCode', 'uom', 'warehouseCode', 'locationCode', 'availableQuantity', 'palletId'] as $field) {
            if (! isset($columns[$field])) { throw new RuntimeException("Required Excel column [{$field}] is missing."); }
        }
        $date1904 = $this->reader->uses1904Dates($path);
        $records = [];
        $skipped = 0;
        $sourceRows = 0;
        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($v) => $this->text($v) !== '')) === 0) { continue; }
            $sourceRows++;
            $line = $index + 2;
            $get = fn ($field) => isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
            $location = strtoupper($this->text($get('locationCode')));
            if ($location === '') { throw new RuntimeException("Location is missing at Excel row {$line}."); }

            $available = $this->quantity($get('availableQuantity'), $line);
            $physical = isset($columns['onHandQuantity']) ? $this->quantity($get('onHandQuantity'), $line) : $available;
            $status = strtoupper($this->text($get('status')));
            if (! in_array($status, ['', 'ACTIVE', 'AVAILABLE', 'OK'], true)) { $available = 0.0; }
            $record = [
                'batchId' => $this->text($get('batchId')), 'clientCode' => strtoupper($this->text($get('clientCode'))),
                'itemCode' => strtoupper($this->text($get('itemCode'))), 'warehouseCode' => strtoupper($this->text($get('warehouseCode'))),
                'locationCode' => $location, 'uom' => strtoupper($this->text($get('uom'))),
                'availableQuantity' => $available, 'onHandQuantity' => $physical,
                'expiryDate' => $this->date($get('expiryDate'), $date1904, $line),
                'receivedDate' => $this->date($get('receivedDate'), $date1904, $line),
                'palletId' => $this->text($get('palletId')) ?: 'SAMPLE-ROW-'.$line,
            ];
            foreach (['batchId', 'clientCode', 'itemCode', 'uom', 'warehouseCode'] as $field) {
                if ($record[$field] === '') { throw new RuntimeException("Identifier [{$field}] is missing at Excel row {$line}."); }
            }
            if ($available > $physical) { throw new RuntimeException("Available quantity exceeds physical stock at Excel row {$line}."); }
            $key = json_encode(array_intersect_key($record, array_flip(['batchId', 'clientCode', 'itemCode', 'warehouseCode', 'locationCode', 'uom', 'expiryDate', 'palletId'])));
            if (isset($records[$key])) {
                $records[$key]['availableQuantity'] += $available;
                $records[$key]['onHandQuantity'] += $physical;
            } else { $records[$key] = $record; }
        }
        // A copy or edit during the read cannot produce a page with a stale fingerprint.
        if (! hash_equals(hash('sha256', $contents), hash_file('sha256', $path))) {
            throw new RuntimeException('The Excel file changed while being read; retry after copying finishes.');
        }
        clearstatcache(true, $path);

        return [
            'asOf' => Carbon::createFromTimestampUTC(filemtime($path))->toIso8601String(),
            'data' => array_values($records),
            '_importReport' => ['source' => 'xlsx', 'sourceRows' => $sourceRows, 'skippedRows' => $skipped],
        ];
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        return in_array(strtoupper($text), ['NULL', 'NAN', 'NONE'], true) ? '' : $text;
    }

    private function quantity(mixed $value, int $line): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new RuntimeException("Invalid inventory quantity at Excel row {$line}.");
        }
        return (float) $value;
    }

    private function date(mixed $value, bool $date1904, int $line): ?string
    {
        $value = $this->text($value);
        if ($value === '') { return null; }
        if (is_numeric($value)) {
            if ((float) $value < 0 || (float) $value > 2958465) { throw new RuntimeException("Invalid date at Excel row {$line}."); }
            $serial = (int) floor((float) $value);
            $epoch = $date1904 ? '1904-01-01' : ($serial < 60 ? '1899-12-31' : '1899-12-30');
            return Carbon::parse($epoch, 'UTC')->addDays($serial)->toDateString();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { throw new RuntimeException("Use Excel dates or YYYY-MM-DD at row {$line}."); }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'UTC');
            if ($date->format('Y-m-d') !== $value) { throw new \Exception; }
            return $date->toDateString();
        } catch (\Throwable) { throw new RuntimeException("Invalid date at Excel row {$line}."); }
    }
}
