# Core WMS

Core WMS is a Laravel simulator used to demonstrate how an external warehouse management system calls **The Fifteen's Agent Manager** to access Intelligent Slotting, Wave Planning and Route Optimisation, and Demand Forecasting.

The `/` page is a test harness. Load a sample or paste a JSON payload, submit it without specifying an agent, and inspect the selected agent and its response. Wave planning responses also display SVG location maps when these are included in the response.

## Prerequisites

- Git and Docker Desktop, with Docker running and Linux containers enabled.
- A working local setup of The Fifteen, including its environment configuration and database setup.
- A copy of this Core WMS project.

Core WMS runs in its own Docker container and joins The Fifteen's Docker network. The external network configured in Core WMS's `docker-compose.yml` or `compose.yaml` must match the network created by The Fifteen.

## 1. Start The Fifteen

Open PowerShell in your **The-Fifteen** project folder:

```powershell
docker compose up -d
docker compose ps
```

Check that the `iwms` and `ai-modules` services are running. The Agent Manager needs the AI service to execute module requests.

## 2. Configure Core WMS

Open another PowerShell window in your **Core-WMS** project folder.

If `.env` does not exist, copy the example file:

```powershell
Copy-Item .env.example .env
```

Do not overwrite an existing `.env`.

Check the Agent Manager settings in `.env`:

```dotenv
AGENT_MANAGER_BASE_URL=http://iwms:8000/api/agent-manager
AGENT_MANAGER_TOKEN=your-shared-agent-token
AGENT_MANAGER_TIMEOUT=120
```

`AGENT_MANAGER_TOKEN` must match the value in **The-Fifteen/iwms/.env**. If your examples contain `change-me-agent-token`, replace it with the same shared value in both projects.

The address above is for direct HTTP communication between containers. If your setup uses an HTTPS proxy instead, use its reachable Docker hostname and full Agent Manager URL. `localhost` inside the Core WMS container refers to Core WMS itself.

For a local HTTPS setup using a self-signed certificate, the client configuration supports:

```dotenv
AGENT_MANAGER_VERIFY_SSL=false
```

Use this only for local testing. For a trusted HTTPS certificate, use `true`.

## 3. Build and start Core WMS

From the **Core-WMS** folder:

```powershell
docker compose up -d --build
docker compose ps
```

Open **http://localhost:8100**.

To use another port, set this in Core WMS's `.env`, then run `docker compose up -d` again:

```dotenv
CORE_WMS_FORWARD_PORT=8200
```

Then open **http://localhost:8200**.

## 4. Test automatic routing

1. Open the test harness.
2. Load a sample for one of the three agents, or paste a valid JSON request.
3. Replace sample client codes, item codes and order details with data available in your local iWMS database.
4. Submit the request without naming an agent.
5. Check the selected agent, response status and returned data.
6. For wave planning, check the location maps when returned.

The Agent Manager identifies the agent from the request structure:

| Agent | Required top-level fields |
| --- | --- |
| Intelligent Slotting | `clientCode`, `items` |
| Wave Planning and Route Optimisation | `orders` |
| Demand Forecasting | `clientCode`, `itemCode`, `forecastDays` |

Use `GET /api/agent-manager` on The Fifteen to inspect the current routing contracts. Valid input structure does not guarantee that the requested client or SKU is supported by the database or forecasting model.

## How the call works

`app/Services/AgentManagerClient.php` handles communication with The Fifteen and sends the shared token in the `X-Agent-Token` header.

For automatic routing, the harness posts the JSON payload to:

```text
POST {AGENT_MANAGER_BASE_URL}
```

The Agent Manager validates the request, selects the matching agent, executes the module and returns the response for the harness to display.

Explicit routing uses `POST {AGENT_MANAGER_BASE_URL}/{agent}`. Consult the current contracts and route configuration for supported agent names.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| Docker reports that an external network does not exist | Start The Fifteen first and compare the network name in both Compose configurations. Use `docker network ls` to inspect available networks. |
| Connection failure or hostname resolution error | Check that both apps share the configured network and that `AGENT_MANAGER_BASE_URL` uses a reachable service hostname. |
| `401 Unauthorized` | Check that the tokens match. Recreate the affected containers after changing environment settings. |
| SSL certificate error | Check the HTTPS URL and certificate. For local self-signed certificates, check `AGENT_MANAGER_VERIFY_SSL=false`. |
| `422` validation response | Check the current input contract, required fields, nested items and quantities. |
| Module error or timeout | Check that `ai-modules` is running, inspect its logs and verify the requested data exists. |
| Port already in use | Change `CORE_WMS_FORWARD_PORT` and recreate Core WMS. |

Inspect Core WMS logs from its project folder:

```powershell
docker compose logs --tail=100
```

Inspect the AI service from The Fifteen's project folder:

```powershell
docker compose logs --tail=100 ai-modules
```

After changing environment values, run this in the affected project folder to recreate its containers:

```powershell
docker compose up -d --force-recreate
```

If Laravel still uses cached configuration, run `php artisan optimize:clear` inside its application container. Use the service name shown by `docker compose ps`:

```text
docker compose exec <laravel-service-name> php artisan optimize:clear
```

## Notes

- Core WMS is a demonstration and testing simulator.
- Sessions and cache use the filesystem; Core WMS does not require its own database in this setup.
- The views use Blade and inline CSS. There is no frontend build step or `npm run dev` process to start.
- SVG location maps are rendered as images through a `data:` URI. Map content, including route paths or arrows, depends on the response produced by the installed iWMS version.