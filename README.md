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
AGENT_MANAGER_CLIENT_ID=iwms_...
AGENT_MANAGER_SECRET=...
AGENT_MANAGER_TIMEOUT=120
```

The client ID and secret are issued by The Fifteen. Run this once there and copy both values; the secret is shown only once:

```bash
docker exec the-fifteen-iwms-1 php artisan api-clients:create core-wms
```

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
| Intelligent Slotting | `clientCode`, `item` |
| Wave Planning and Route Optimisation | `pickingListId`, `warehouseZone`, `orders` |
| Demand Forecasting | `clientCode`, `itemCode`, `forecastDays` |

Use `GET /api/agent-manager` on The Fifteen to inspect the current routing contracts. Valid input structure does not guarantee that the requested client or SKU is supported by the database or forecasting model.

## How the call works

`app/Services/AgentManagerClient.php` handles communication with The Fifteen and signs every request with the client secret (HMAC-SHA256 over the method, path, timestamp, nonce and body hash), sent as the `X-Client-Id`, `X-Request-Timestamp`, `X-Request-Nonce` and `X-Request-Signature` headers. The secret itself never leaves Core WMS.

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
| `401 Unauthorized` | Check `AGENT_MANAGER_CLIENT_ID` and `AGENT_MANAGER_SECRET` against the client issued by The Fifteen (`php artisan api-clients:list` there), and that the clocks agree within 5 minutes. Recreate the affected containers after changing environment settings. |
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

## Inventory API testing with iWMS

The client sends its normal Agent Manager request. iWMS retrieves current
inventory from Core WMS through `GET /api/inventory` and returns the AI result:

```text
Core WMS UI -> iWMS Agent Manager -> Core WMS inventory API -> Excel workbook
                         |
                         +-> AI module -> response displayed in Core WMS
```

Client JSON is unchanged: there is no inventory source selector or filename in
the request. Core WMS supplies current inventory only; iWMS retains its layout,
item master, history, and models. Normal iWMS module pages retain their local
inventory flow.

### Workbook configuration

Place the workbook in `storage/app/inventory/`. The current simulation uses
`Inventory Snapshot 20260417 1625.xlsx`. Set this project's `.env` to:

```dotenv
WMS_INVENTORY_SNAPSHOT_PATH="/var/www/html/storage/app/inventory/Inventory Snapshot 20260417 1625.xlsx"
WMS_INVENTORY_WORKSHEET=INVENTORY
WMS_INVENTORY_API_TOKEN=<inventory bearer token>
```

The path is inside the Docker container, not a Windows host path. To rename or
replace the workbook, update this setting to the new container path. When the
setting is blank, the default is `storage/app/inventory/inventory.xlsx`.
Only the configured file is read; other Excel files in the folder are not merged.
No JSON files, location mapping file, import command, or simulation database are
required for this Excel-backed setup.

After changing `.env`, run from **Core-WMS**:

```powershell
docker compose up -d --force-recreate core-wms
docker compose exec -T core-wms php artisan config:clear
```

Editing the contents of the active workbook does not require a restart. Save it
before submitting another request; the API reads it again on each retrieval.

### Connect iWMS

In **The-Fifteen/iwms/.env**:

```dotenv
AGENT_MANAGER_INVENTORY_SOURCE=api
```

In **The-Fifteen/ai-modules/.env**:

```dotenv
CORE_WMS_INVENTORY_URL=http://core-wms:8000/api/inventory
CORE_WMS_INVENTORY_TOKEN=<match WMS_INVENTORY_API_TOKEN>
CORE_WMS_INVENTORY_TIMEOUT=60
```

Apply these settings from **The-Fifteen**:

```powershell
docker compose up -d --no-deps --force-recreate iwms ai-modules
docker compose exec -T iwms php artisan config:clear
```

Both projects must join `the-fifteen_default`. Inventory tokens are independent
of `AGENT_MANAGER_CLIENT_ID` and `AGENT_MANAGER_SECRET`, which authenticate signed
client requests. Keep the configured four WMS PHP workers and `serve --no-reload`:
a UI request waits for iWMS while iWMS calls Core WMS back for inventory. A single
worker would block that callback.

### Test from the browser

1. Open `http://localhost:8100`.
2. Click **Check inventory connection**. It displays the active workbook and row
   counts, and confirms Agent Manager is configured for API inventory. A failed
   check does not claim the connection is ready. This checks configuration and
   workbook readability; submit a request to exercise the complete AI flow.
3. Load a sample or enter the existing client JSON and submit it. The automatic
   routing page chooses the agent from the input fields. Dedicated forms remain
   available at `/slotting`, `/wave-planning`, and `/forecasting`.
4. Inspect the response. For a controlled stock test, change an item's available
   quantity in the active workbook, save, and repeat a forecasting request.
   Inventory affects projected stock and reorder advice, not demand prediction.

The dedicated slotting form sends one `clientCode` + `item` request per entered
row. Wave requests use `pickingListId`, `warehouseZone`, and `orders`; the dedicated
endpoint uses the `wave-planning-and-route-optimization` slug.

API inventory failures return an error and do not fall back to iWMS stock. Wave
planning reports inventory locations absent from iWMS's layout as unroutable.
API waves are recommendations; they do not reserve local iWMS stock or create
executable local waves.

### Switch inventory sources later

Source selection belongs to iWMS, not the Core WMS form. To use local iWMS
inventory for external Agent Manager requests, set
`AGENT_MANAGER_INVENTORY_SOURCE=local` in **The-Fifteen/iwms/.env**, then run from
**The-Fifteen**:

```powershell
docker compose up -d --no-deps --force-recreate iwms
docker compose exec -T iwms php artisan config:clear
```

Set it back to `api` and repeat to restore Core WMS inventory. Client inputs do
not change. The Core WMS connection check reports not ready in local mode because
it specifically verifies the Excel/API simulation. Normal iWMS module pages
retain local inventory in both modes.

### Excel columns and inventory response

Required headers: `batch ID`, `SKU ID`, `uom`, `warehouse ID`,
`available quantity`, `Location Code`, `pallet ID`, `Client ID`.
Optional headers: `expiry`, `received date`, `status`, `on hand quantity`.
Column order may change. Excel serial dates (1900/1904 systems) and YYYY-MM-DD
are supported. Duplicate stock keys are combined. Held/inactive stock has zero
available quantity while its physical quantity is retained. Without an on-hand
column, physical stock is approximated by the available quantity column.
Missing pallet IDs receive simulation row identifiers.

The API returns all valid locations from the workbook. Location resolution
belongs to iWMS; no layout JSON is needed in Core WMS. The endpoint accepts optional
`clientCode` / `itemCode` filters and an opaque pagination cursor, returning
`asOf`, `data`, and `nextCursor`. The page size is 5,000 inventory records.
Missing/invalid snapshots return HTTP 503, invalid cursors 422, changed snapshots
or filters during pagination 409, and invalid inventory tokens 401.
`X-WMS-Inventory-Source: xlsx` identifies Excel responses. Source/skipped row
counts are also returned in response headers. Inventory responses are not cached.

Excel files are local simulation data and remain Git-ignored. Copy the workbook
separately when setting up another developer; do not commit operational inventory.
This simulator reads Excel directly, rather than populating the separate database
schema suggested as an alternative simulation approach.

Run the focused UI/API checks from **Core-WMS**:

```powershell
docker compose exec -T core-wms php artisan test --filter="InventoryApiTest|ExcelInventoryApiTest|InventoryCheckTest|InventorySourceFormsTest"
```
