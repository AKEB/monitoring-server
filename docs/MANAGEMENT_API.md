# Management API (`/api/v2`) and MCP

Full management surface for monitors, notify channels, signatures, workers, maintenances, dockers, users/groups (read-only), audit logs, and object permissions.

`GET /api/v1/status/` is unchanged: it only returns monitors with **Expose in /api/v1/status/** (`api_enabled`) plus read ACL. Do not turn that flag on for every monitor.

## Auth

Same personal tokens as status API (`/settings/api/`):

```
Authorization: Bearer mnt_...
```

or `X-Api-Token: mnt_...`

Rights = the token owner’s UI ACL. Rate limit for v2/MCP is softer than status (~120 req/min per token).

## REST

Base: `/api/v2/{resource}/` and `/api/v2/{resource}/{id}/`

| Resource | Methods |
|----------|---------|
| `monitors` | GET list/get, POST, PATCH, DELETE |
| `notify` | GET list/get, POST, PATCH, DELETE |
| `signatures` | GET list/get, POST, PATCH, DELETE |
| `workers` | GET list/get, POST, PATCH, DELETE |
| `maintenances` | GET list/get, POST, PATCH, DELETE |
| `dockers` | GET list/get, POST, PATCH, DELETE |
| `users` | GET only |
| `groups` | GET only |
| `logs` | GET (`from`, `to` as `YYYY-MM-DD`) |
| `{resource}/{id}/permissions` | GET / PUT object ACL |

Envelope:

```json
{ "status": 0, "data": ..., "meta": { "total": 1 } }
```

Errors: `{ "status": 1, "error": "..." }` with HTTP 401/403/404/422/429.

Secrets (`notify` tokens, `worker_key_hash`) are redacted unless the caller has the matching secret permission. Docker TLS PEM blobs are write-only: send PEM or base64 in `tls_ca_file` / `tls_certificate` / `tls_key`; responses expose only `tls_present`.

Monitors accept the same fields as the UI edit form, including type-specific options, `workers[]`, `notify[]`, `signature_id`, and `api_enabled` (status feed opt-in only). Workers accept tuning timeouts, `monitors[]`, `dockers[]`, and `restart_worker`. Dockers accept `workers[]` and TLS fields.

## MCP

Remote endpoint: `POST /mcp/` (JSON-RPC).

Same Bearer token. Tools mirror v2 (`monitors_list`, `monitors_create`, …, `logs_list`, `permissions_set`). Server `instructions` and tool descriptions tell the agent to respect ACL and keep `/api/v1/status/` opt-in.

Example Cursor MCP config:

```json
{
  "mcpServers": {
    "monitoring": {
      "url": "https://monitoring.example/mcp/",
      "headers": {
        "Authorization": "Bearer mnt_YOUR_TOKEN"
      }
    }
  }
}
```

## Tests

```bash
cd src && vendor/bin/phpunit -c phpunit.xml
```

Unit tests cover domain helpers, status opt-in regression, Api\V2 services (in-memory), and MCP tool catalog/routing.
