<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

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
        $request = Http::timeout((int) config('services.agent_manager.timeout'))
            ->acceptJson()
            // Signed on the final request, so the body and path are exactly what
            // goes over the wire, with a fresh nonce on every send.
            ->withRequestMiddleware(fn (RequestInterface $request): RequestInterface => $this->sign($request));

        if (! config('services.agent_manager.verify_ssl', true)) {
            $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * HMAC-SHA256(secret, METHOD \n PATH?QUERY \n TIMESTAMP \n NONCE \n SHA256(BODY)),
     * the canonical form The Fifteen verifies (App\Support\Security\SignedRequest
     * in its iwms app). Change one and the other must change with it.
     */
    private function sign(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $path = $uri->getQuery() === '' ? $uri->getPath() : $uri->getPath().'?'.$uri->getQuery();
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        $signature = hash_hmac('sha256', implode("\n", [
            strtoupper($request->getMethod()),
            $path,
            $timestamp,
            $nonce,
            hash('sha256', (string) $request->getBody()),
        ]), (string) config('services.agent_manager.secret'));

        return $request
            ->withHeader('X-Client-Id', (string) config('services.agent_manager.client_id'))
            ->withHeader('X-Request-Timestamp', $timestamp)
            ->withHeader('X-Request-Nonce', $nonce)
            ->withHeader('X-Request-Signature', $signature);
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
