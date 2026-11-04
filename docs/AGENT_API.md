# Host agent API

Push ingest for [monitoring-agent](https://github.com/AKEB/monitoring-agent).

## Endpoint

`POST /api/agent/report/`

Content-Type: `application/json`

No CSRF. Auth via `agent_key_hash` in the JSON body (create a Server in Admin → Servers).

## Request (Phase 1+)

```json
{
  "agent_key_hash": "...",
  "agent_version": "v1.0.0",
  "collected_at": 1730736000,
  "host": { "hostname": "...", "os": "linux", "platform": "debian", "platform_version": "12", "kernel": "...", "arch": "amd64", "uptime_sec": 123, "virtualization": "kvm" },
  "cpu": { "cores": 4, "load1": 0.2, "load5": 0.3, "load15": 0.1, "usage_pct": 12.5 },
  "memory": { "total_bytes": 1, "used_bytes": 1, "available_bytes": 1, "used_pct": 40.0 },
  "swap": { "total_bytes": 0, "used_bytes": 0, "used_pct": 0 },
  "disks": [{ "mount": "/", "device": "/dev/sda1", "fstype": "ext4", "total_bytes": 1, "used_bytes": 1, "free_bytes": 1, "used_pct": 55.0 }],
  "network": { "interfaces": [{ "name": "eth0", "addrs": ["1.2.3.4/24"], "rx_bytes": 1, "tx_bytes": 1, "rx_bps": 100, "tx_bps": 50 }] },
  "docker": { "available": true, "containers": [] },
  "auth_events": [],
  "processes": []
}
```

## Response

```json
{ "status": 0, "server_id": 1 }
```

Errors: `status: 1` with HTTP 400/404.

## Install

See [monitoring-agent README](https://github.com/AKEB/monitoring-agent#install-systemd).

1. Admin → Servers → Create → copy `AGENT_KEY_HASH`
2. Install binary + systemd on the host
3. Set `SERVER_HOST` and `AGENT_KEY_HASH` in `.env`
4. Optional monitors: types Server offline / CPU / memory / disk linked to this server

## Platforms

Proxmox VE, Debian, Raspberry Pi OS, LXC, Proxmox Backup Server — same binary (`linux-amd64` or `linux-arm64`).
