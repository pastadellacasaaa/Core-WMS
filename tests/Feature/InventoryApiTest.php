<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    private string $snapshot;
    private array $rows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshot = tempnam(sys_get_temp_dir(), 'wms-stock-');
        $this->rows = array_map(fn ($id) => [
            'batchId' => 'B'.$id, 'clientCode' => $id === 3 ? 'OTHER' : 'SCS',
            'itemCode' => 'SKU-'.$id, 'warehouseCode' => 'A', 'locationCode' => 'AFRZ1-A-01-01',
            'uom' => 'KG', 'availableQuantity' => 5, 'onHandQuantity' => 10, 'palletId' => 'P'.$id,
            'expiryDate' => null, 'receivedDate' => null,
        ], [1, 2, 3]);
        config(['inventory.snapshot_path' => $this->snapshot, 'inventory.token' => 'test-secret', 'inventory.page_size' => 2]);
        $this->writeSnapshot();
    }

    protected function tearDown(): void
    {
        if (is_file($this->snapshot)) { unlink($this->snapshot); }
        parent::tearDown();
    }

    private function writeSnapshot(): void
    {
        file_put_contents($this->snapshot, json_encode(['asOf' => '2026-10-20T10:00:00+08:00', 'data' => $this->rows]));
    }

    public function test_requires_the_inventory_token(): void
    {
        $this->getJson('/api/inventory')->assertUnauthorized();
        $this->withToken('wrong')->getJson('/api/inventory')->assertUnauthorized();
    }

    public function test_returns_all_pages_with_stable_timestamp_and_required_fields(): void
    {
        $first = $this->withToken('test-secret')->getJson('/api/inventory')->assertOk()->assertJsonCount(2, 'data');
        $cursor = $first->json('nextCursor');
        $this->assertNotEmpty($cursor);
        $this->withToken('test-secret')->getJson('/api/inventory?'.http_build_query(['cursor' => $cursor]))
            ->assertOk()->assertJsonPath('asOf', $first->json('asOf'))->assertJsonCount(1, 'data')
            ->assertJsonPath('nextCursor', null)->assertJsonPath('data.0.palletId', 'P3');
    }

    public function test_filters_case_insensitively_and_returns_empty_stock(): void
    {
        $this->withToken('test-secret')->getJson('/api/inventory?clientCode=scs&itemCode=sku-2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.availableQuantity', 5);
        $this->getJson('/api/inventory?itemCode=missing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rejects_changed_snapshot_or_filters_during_pagination(): void
    {
        $cursor = $this->withToken('test-secret')->getJson('/api/inventory')->json('nextCursor');
        $this->getJson('/api/inventory?'.http_build_query(['cursor' => $cursor, 'clientCode' => 'SCS']))->assertStatus(409);
        $this->rows[0]['onHandQuantity'] = 12;
        $this->writeSnapshot();
        $this->getJson('/api/inventory?'.http_build_query(['cursor' => $cursor]))->assertStatus(409);
    }

    public function test_missing_invalid_duplicate_or_overallocated_inventory_fails_closed(): void
    {
        file_put_contents($this->snapshot, 'broken');
        $this->withToken('test-secret')->getJson('/api/inventory')->assertStatus(503);
        $this->rows[] = $this->rows[0];
        $this->writeSnapshot();
        $this->getJson('/api/inventory')->assertStatus(503);
        array_pop($this->rows);
        $this->rows[0]['availableQuantity'] = 20;
        $this->writeSnapshot();
        $this->getJson('/api/inventory')->assertStatus(503);
        unlink($this->snapshot);
        $this->getJson('/api/inventory')->assertStatus(503);
    }

    public function test_invalid_cursor_is_rejected(): void
    {
        $this->withToken('test-secret')->getJson('/api/inventory?cursor=bad')->assertUnprocessable();
    }
}