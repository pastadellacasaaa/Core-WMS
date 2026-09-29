<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoRouteControllerTest extends TestCase
{
    public function test_renders_the_actual_wave_svg_below_the_response(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.agent_manager.base_url' => 'http://agent.test']);
        $svg = '<svg><rect data-location="AFRZ1-A-02-03"/></svg>';

        Http::fake([
            'http://agent.test' => Http::sequence()
                ->push([
                    'agent' => 'wave-planning-and-route-optimization',
                    'pickingLists' => [[
                        'maps' => [[
                            'waveCode' => 'AFRZ1-260910-0001',
                            'widthPx' => 160,
                            'heightPx' => 328,
                            'svg' => $svg,
                            'locations' => [['locationCode' => 'AFRZ1-A-02-03']],
                        ]],
                    ]],
                ])
                ->push(['data' => []]),
        ]);

        $response = $this->post('/', [
            'payload' => json_encode(['orders' => []]),
        ]);

        $response
            ->assertSee('Completed location map for wave AFRZ1-260910-0001')
            ->assertSee('data:image/svg+xml;base64,'.base64_encode($svg), false);
    }
}
