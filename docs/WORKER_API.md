# Worker API (protocol 2.0+)

Go worker `monitoring-worker-go` v2.1+ talks to monitoring-server v2.1+ only.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/monitoring/get/` | Tasks + worker config |
| POST | `/api/monitoring/state/` | Check results |

Authentication: `worker_key_hash` in JSON body.

## Protocol version

- Required: `protocol_version` ≥ `2.0` (worker default `2.0`).
- Older PHP worker and protocol `1.0` are not supported.

## `get/` request

```json
{
  "worker_key_hash": "...",
  "protocol_version": "2.0",
  "worker_version": "v2.1.0",
  "docker_tls_sync": { "12": 1715000000 }
}
```

`docker_tls_sync`: docker host id → `docker_update_time` last seen; server omits `tls_*` PEM when unchanged.

## `get/` response

- `data`: worker tuning + optional `"restart": "true"` (one-shot, from admin **Restart worker**).
- `jobs[]`: monitor tasks (whitelist fields, includes `proxy_type`). Monitors in active maintenance are omitted. Worker-executed types include HTTPS/JSON, TCP (`2`), ping, exporters, and UDP Port (`18`: host+port listen probe — ICMP unreachable = fail, timeout/reply = ok; not a protocol handshake). Type `17` (server listening port) is agent-side, not dispatched here.
- `dockers[]`: docker tasks (`docker_update_time`, conditional TLS PEM). Omitted when every enabled container monitor on that host is in maintenance.

No `server_time` / `server_microtime`.

## `state/` request

- Monitors: `jobs[]` with `response` (`status_code`, `json_valid`, `cert_expire`, `response_error`, `response_error_num`, …). No `response_body`.
- Docker: `dockers[]` with `response.status_code` (HTTP status from Engine API), `response.container_states` (not raw Engine API `body`), and optional `response.engine_info` from Engine `GET /version` (`Version`, `ApiVersion`, `MinAPIVersion`, `Os`, `Arch`, `KernelVersion`). Server stores per-worker poll outcome on `monitoring_dockers` and, on success, updates `dockers.container_states` / `last_success_*` / `engine_info` (does not overwrite `dockers.update_time`). Container monitor logs store that HTTP code in `monitoring_logs.status_code` (UI tooltips).
- Omit empty `jobs` / `dockers` keys when not sending that kind of result.

### HTTP probe `response` semantics

- `status_code`: real HTTP status when a response was received; **`0` means no HTTP response** (DNS/dial/TLS/timeout). Transport failures are never remapped to `502`/`504`.
- `response_error`: human-readable reason (truncated ~240 chars). For HTTP 4xx/5xx: `HTTP 502 Bad Gateway` plus optional body snippet; overridden by response header `Log:` when present.
- `response_error_num` for transport failures (no HTTP response):
  - `408` — timeout
  - `601` — DNS
  - `603` — connection refused
  - `604` — network unreachable
  - `605` — connection reset
  - `606` — TLS / certificate
  - `2` — other transport error
  - for a real HTTP error response, equals `status_code` (e.g. `502`)

## Deployment order

1. Run DB migration `migration_0042` (docker poll status fields) after earlier migrations.
2. Deploy monitoring-server.
3. Deploy workers with matching protocol (engine_info requires current worker-go).
