# Core WMS

A Laravel app that stands in for the Core WMS, calling The Fifteen's **Agent Manager**
to reach the three AI agents. It runs in its own Docker container, on The Fifteen's
Docker network.

The `/` page is a single test harness: paste or load a sample payload, submit it without
naming an agent, and see which agent the manager selected and what it returned. Wave
planning responses also display their completed SVG location maps below the JSON.

## Prerequisites

The Fifteen must be running first — Core WMS joins its Docker network and calls the
Agent Manager at the internal address `http://iwms:8000`:

```bash
cd ../The-Fifteen && docker compose up -d
```

## Run

```bash
docker compose up -d --build
```

Open http://localhost:8100.

`AGENT_MANAGER_TOKEN` in `.env` must match the same variable in `The-Fifteen/iwms/.env`.
Both default to `change-me-agent-token`.

To use a different port:

```bash
CORE_WMS_FORWARD_PORT=8200 docker compose up -d
```

## How the call is made

`app/Services/AgentManagerClient.php` is the only place that talks to The Fifteen. It
posts to `{AGENT_MANAGER_BASE_URL}/{agent}` with the shared secret in an `X-Agent-Token`
header. The three controllers validate the form, hand the payload to that client, and
render whatever comes back.

Agent names are the module slugs:

- `intelligent-slotting`
- `wave-planning-and-route-optimization`
- `demand-forecasting`

`GET /api/agent-manager` on The Fifteen lists them.

## Notes

- Sessions and cache are on the filesystem; nothing here uses a database yet.
- No frontend build step — the views are plain Blade with inline CSS, so there is no
  `npm run dev` to keep running.
- The wave planning completed-style location map arrives as an SVG with no travel path
  and is rendered through a `data:` URI, which keeps any script inside it inert.
