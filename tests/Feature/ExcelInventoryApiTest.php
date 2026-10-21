<?php

namespace Tests\Feature;

use Tests\TestCase;
use ZipArchive;

class ExcelInventoryApiTest extends TestCase
{
    private string $directory;
    private string $xlsx;
    private array $headers = ['batch ID', 'SKU ID', 'uom', 'warehouse ID', 'available quantity', 'expiry', 'status', 'received date', 'Location Code', 'pallet ID', 'Client ID'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/wms-excel-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->xlsx = $this->directory.'/inventory.xlsx';
        file_put_contents($this->directory.'/locations.json', json_encode(['AFRZ1-A-01-01' => 'A']));
        config(['inventory.snapshot_path' => $this->xlsx, 'inventory.location_map_path' => $this->directory.'/locations.json', 'inventory.token' => '', 'inventory.page_size' => 5000]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) { unlink($path); }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function row(float $quantity = 5, string $status = 'Active', string $location = 'AFRZ1-A-01-01'): array
    {
        return [20280505, 'SKU-1', 'KG', 'WH01', $quantity, 46023, $status, '2026-01-01', $location, 'PALLET-1', 'SCS'];
    }

    private function workbook(array $rows, bool $date1904 = false, bool $sharedStrings = false): void
    {
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$this->headers, ...$rows] as $r => $values) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach ($values as $c => $value) {
                if ($value === null) { continue; }
                $ref = chr(65 + $c).($r + 1);
                if ($sharedStrings && $r === 1 && $c === 1) {
                    $sheet .= '<c r="'.$ref.'" t="s"><v>0</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $sheet .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip = new ZipArchive;
        $zip->open($this->xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="'.($date1904 ? '1' : '0').'"/><sheets><sheet name="INVENTORY" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="/xl/worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        if ($sharedStrings) { $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>SKU</t></r><r><t>-1</t></r></si></sst>'); }
        $zip->close();
    }

    public function test_reads_original_excel_columns_shared_strings_dates_and_warehouse_mapping(): void
    {
        $this->workbook([$this->row()], sharedStrings: true);
        $this->getJson('/api/inventory')->assertOk()->assertHeader('X-WMS-Inventory-Source', 'xlsx')
            ->assertHeader('X-WMS-Inventory-Source-Rows', '1')->assertHeader('X-WMS-Inventory-Skipped-Rows', '0')
            ->assertJsonPath('data.0.batchId', '20280505')->assertJsonPath('data.0.itemCode', 'SKU-1')
            ->assertJsonPath('data.0.warehouseCode', 'WH01')->assertJsonPath('data.0.availableQuantity', 5)
            ->assertJsonPath('data.0.expiryDate', '2026-01-01')->assertJsonPath('data.0.receivedDate', '2026-01-01');
    }

    public function test_editing_excel_changes_stock_and_invalidates_old_pagination(): void
    {
        config(['inventory.page_size' => 1]);
        $second = $this->row(); $second[9] = 'PALLET-2';
        $this->workbook([$this->row(), $second]);
        $cursor = $this->getJson('/api/inventory')->json('nextCursor');
        $this->workbook([$this->row(9), $second]);
        $this->getJson('/api/inventory?cursor='.urlencode($cursor))->assertStatus(409);
        $this->getJson('/api/inventory?clientCode=scs&itemCode=sku-1')->assertOk()->assertJsonPath('data.0.availableQuantity', 9);
    }

    public function test_combines_duplicate_rows_keeps_held_stock_physical_and_reports_exclusions(): void
    {
        $held = $this->row(7, 'Held'); $held[9] = 'PALLET-HELD';
        $this->workbook([$this->row(), $this->row(), $held, $this->row(location: 'OFFSITE')]);
        $this->getJson('/api/inventory')->assertOk()->assertHeader('X-WMS-Inventory-Skipped-Rows', '0')
            ->assertJsonCount(3, 'data')->assertJsonPath('data.0.availableQuantity', 10)
            ->assertJsonPath('data.1.availableQuantity', 0)->assertJsonPath('data.1.onHandQuantity', 7);
    }

    public function test_rejects_invalid_quantities_headers_and_dates(): void
    {
        $this->workbook([$this->row(-1)]);
        $this->getJson('/api/inventory')->assertStatus(503);
        $bad = $this->row(); $bad[5] = '2026-02-30';
        $this->workbook([$bad]); $this->getJson('/api/inventory')->assertStatus(503);
        $this->headers[4] = 'unknown quantity';
        $this->workbook([$this->row()]); $this->getJson('/api/inventory')->assertStatus(503);
    }

    public function test_missing_excel_does_not_fall_back_to_json(): void
    {
        file_put_contents($this->directory.'/snapshot.json', json_encode(['asOf' => '2026-10-21T00:00:00+00:00', 'data' => []]));
        $this->getJson('/api/inventory')->assertStatus(503);
    }

    public function test_supports_1904_excel_dates(): void
    {
        $row = $this->row(); $row[5] = 0;
        $this->workbook([$row], date1904: true);
        $this->getJson('/api/inventory')->assertOk()->assertJsonPath('data.0.expiryDate', '1904-01-01');
    }

    public function test_excel_needs_no_location_json_and_keeps_unknown_locations(): void
    {
        $this->workbook([$this->row(location: 'OFFSITE')]);
        unlink($this->directory.'/locations.json');
        $this->getJson('/api/inventory')->assertOk()
            ->assertJsonPath('data.0.locationCode', 'OFFSITE')
            ->assertJsonPath('data.0.warehouseCode', 'WH01');
    }
}