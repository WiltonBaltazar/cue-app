# Contract: Internal JSON Endpoints (`/app-api`)

Used only by TanStack Query inside Inertia pages (research R6). Same-origin, authenticated with
the **Sanctum stateful session** (`auth:sanctum`, CSRF cookie). Not a public API; no tokens
are issued in this phase. All responses `application/json`; all inputs validated by Form
Requests; all resources authorised by Policies.

Rate limit: 60 req/min per user.

## Volunteer-accessible

### `GET /app-api/notifications/unread-count`

```json
{ "count": 3 }
```

### `GET /app-api/notifications?page=1`

```json
{ "data": [{ "id": "uuid", "type": "schedule_published", "title": "…", "url": "/minha-escala", "readAt": null, "createdAt": "2026-10-18T10:00:00Z" }], "nextPage": 2 }
```

### `POST /app-api/notifications/{id}/read` → `204`

### `POST /app-api/push-subscriptions`

Body: standard `PushSubscription.toJSON()` (`endpoint`, `keys.p256dh`, `keys.auth`) +
`deviceLabel` (string ≤ 60). → `201`. `DELETE` same path with `endpoint` → `204`.

### `GET /app-api/swaps/{swap}/eligible` (requester only)

Colleagues eligible to receive a directed swap.

```json
{ "data": [{ "volunteerId": 12, "displayName": "Ana M.", "level": "intermediate" }] }
```

## Coordinator-only (`role:coordinator`)

### `GET /app-api/positions/{position}/candidates?substituir={assignment}`

Ranked candidates for an open place or to replace someone (`substituir`), ordered by
eligibility, number of broken rules, then the generator's ranking (FR-019). Only volunteers
who have the position's role are listed.

```json
{
  "data": [
    { "volunteerId": 7, "name": "João Pedro Sitoe", "level": "Avançado", "recentAssignments": 1, "eligible": true, "violations": [] },
    { "volunteerId": 9, "name": "Rita Cossa", "level": "Iniciante", "recentAssignments": 0, "eligible": false, "violations": ["min_level"] }
  ]
}
```

`violations` values: `availability`, `absence`, `min_level`, `frequency_cap`,
`same_event`, `time_overlap`, `inactive`, `role` (does not have the role at all).

### `POST /app-api/assignments/check`

Dry-run for the grid editor before saving.

Body: `{ "position_id": 1, "volunteer_id": 7 }` →
`{ "ok": false, "violations": ["time_overlap"], "message": "Já está escalado noutro evento à mesma hora" }`

Full names appear here because these endpoints are coordinator-only (FR-031).

### `GET /app-api/coverage?from=2026-11-01&to=2026-11-30`

```json
{ "data": [{ "eventId": 4, "name": "Culto de Domingo", "date": "2026-11-02", "filled": 5, "required": 6, "gaps": [{ "role": "Áudio", "missing": 1 }] }] }
```

### `GET /app-api/availability-matrix?role=audio&minLevel=intermediate`

```json
{ "weekdays": [1,2,3,4,5,6,7], "periods": ["morning","afternoon","evening"], "counts": { "7.morning": 14, "2.evening": 6 }, "shortages": ["audio"] }
```

## Errors

| Status | Body |
|--------|------|
| 401 | `{ "message": "Sessão terminada" }` → client redirects to `/entrar` |
| 403 | `{ "message": "Sem permissão" }` |
| 422 | Laravel validation format `{ message, errors: { field: [..] } }` |
| 429 | `{ "message": "Demasiados pedidos, tente daqui a pouco" }` |

Responses never include phone, e-mail or 🔒 fields of other users.
