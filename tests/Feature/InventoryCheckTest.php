<?php

namespace Tests\Feature;

use App\Services\InventorySnapshot;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryCheckTest extends TestCase
{
    public function test_confirms_excel_inventory_and_server_side_selection(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['inventory' => ['source' => 'api']])]);
        $snapshot = $this->mock(InventorySnapshot::class);
        $snapshot->importReport = ['sourceRows' => 3];
        $snapshot->shouldReceive('page')->once()->with([])->andReturn(['data' => [[], []], 'nextCursor' => null]);
        $this->getJson('/inventory-check')->assertOk()->assertJsonPath('ready', true)
            ->assertJsonPath('sourceRows', 3)->assertJsonPath('recordsOnFirstPage', 2);
    }

    public function test_does_not_claim_ready_when_agent_manager_uses_local_stock(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['inventory' => ['source' => 'local']])]);
        $this->getJson('/inventory-check')->assertStatus(503)->assertJsonPath('ready', false);
    }
}
