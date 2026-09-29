<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls The Fifteen's Agent Manager, the single door to the three AI agents.
 *
 * Returns the raw response so callers can show the Agent Manager's own error
 * message instead of a generic failure.
 */
class AgentManagerClient
{
    /**
     * Run a named agent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function run(string $agent, array $payload): Response
    {
        return $this->request()->post($this->url($agent), $payload);
    }

    /**
     * Send a payload with no agent named and let the Agent Manager work out
     * which agent the fields belong to.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(array $payload): Response
    {
        return $this->request()->post($this->url(), $payload);
    }

    /** Every agent with the fields it takes. */
    public function contracts(): Response
    {
        return $this->request()->get($this->url());
    }

    public function endpoint(string $agent = ''): string
    {
        return $this->url($agent);
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('services.agent_manager.timeout'))
            ->withHeaders([
                'X-Agent-Token' => (string) config('services.agent_manager.token'),
                // The Agent Manager rejects plain HTTP when API_ENFORCE_HTTPS is on.
                // Inside the compose network this hop stands in for the TLS-terminated
                // one a real deployment would make, so declare it as such.
                'X-Forwarded-Proto' => 'https',
            ])
            ->acceptJson();
    }

    /**
     * Built whole rather than through baseUrl, so the agent-less endpoint is the
     * bare URL and not the base with a trailing slash.
     */
    private function url(string $agent = ''): string
    {
        $base = rtrim((string) config('services.agent_manager.base_url'), '/');

        return $agent === '' ? $base : $base.'/'.ltrim($agent, '/');
    }
}
