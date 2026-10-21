<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlottingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_each_item_using_the_current_iwms_contract_without_source_selection(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'Success', 'slottingLocations' => [], 'unallocated' => []])]);
        $this->post('/slotting', [

            'items' => [
                ['clientCode' => 'SFS', 'itemCode' => 'CHILLED-0058', 'quantity' => 5, 'uom' => 'CTNS', 'expiryDate' => '2026-12-31'],
                ['clientCode' => 'SCS', 'itemCode' => 'FROZEN-0027', 'quantity' => 1, 'uom' => 'PKT'],
            ],
        ])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'http://agent.test/intelligent-slotting'
            && $r['clientCode'] === 'SFS' && $r['item']['itemCode'] === 'CHILLED-0058'
            && ! array_key_exists('inventorySource', $r->data()) && ! isset($r['items']) && ! isset($r['item']['clientCode']));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['clientCode'] === 'SCS' && $r['item']['itemCode'] === 'FROZEN-0027');
    }
}