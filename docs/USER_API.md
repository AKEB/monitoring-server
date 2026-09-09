# User Status API

JSON API for current monitor status and short-window availability.

Expected usage: poll about once per minute. Responses are cached for ~20 seconds; more than one request per 5 seconds with the same token returns HTTP 429.

## Auth

Create a token on `/settings/api/`. The plaintext value is shown **once**.

Send it as:

```
Authorization: Bearer mnt_...
```

or

```
X-Api-Token: mnt_...
```

Session cookies are ignored. Inactive users and revoked tokens get HTTP 401.

## Endpoint

`GET /api/v1/status/`

## Access

The token acts as the user who created it:

- only monitors with **read** permission (same ACL as the UI, including folder inheritance)
- only monitors with **Expose in API** enabled (opt-in, default off)
- `description_ru` / `description_en` come from the monitor edit form (shown when the flag is on)
- folders, disabled monitors, and disabled parent folders are omitted
- administrators still need the expose flag; they are not shown every monitor

## Status values

| Value | Meaning |
|--------|---------|
| `up` | All assigned workers with fresh data succeeded |
| `down` | At least one assigned worker failed (mixed workers are `down`) |
| `maintenance` | Active planned maintenance (inherits from parent folders) |
| `unknown` | No fresh checks in the last `2 × repeat_seconds` |

## Example

```bash
curl -sS -H "Authorization: Bearer mnt_YOUR_TOKEN" https://monitoring.example/api/v1/status/
```

```json
{
  "status": 0,
  "generated_at": 1756890000,
  "resources": [
    {
      "id": 12,
      "title": "example.com",
      "description_ru": "Проверка главной страницы",
      "description_en": "Homepage health check",
      "check_url": "https://example.com/health",
      "status": "up",
      "availability": {
        "1m": 100,
        "5m": 100,
        "10m": 99.5,
        "1h": 99.8,
        "4h": 99.91,
        "8h": 99.93
      },
      "workers": [
        {
          "id": 3,
          "title": "msk-1",
          "status": "up",
          "availability": {
            "1m": 100,
            "5m": 100,
            "10m": 100,
            "1h": 99.7,
            "4h": 99.9,
            "8h": 99.92
          }
        }
      ]
    }
  ]
}
```

`availability` windows with no checks are `null` (common for `1m` when `repeat_seconds` is 60 or more). Percentages are `good / total` over raw `monitoring_logs` in that window, rolled up across workers as a sum of checks.

Errors:

```json
{ "status": 1, "error": "Unauthorized" }
```

HTTP codes: `401` unauthorized, `429` rate limited.
