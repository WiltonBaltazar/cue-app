# Data Model: Cue App (001-cue-app)

**Date**: 2026-10-18 | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

MySQL 8.4, InnoDB, `utf8mb4`. All tables have `id` (bigint PK) and `created_at` / `updated_at`
unless noted. Timestamps are UTC; dates (`date`) are local Africa/Maputo calendar dates.
🔒 = stored with Laravel `encrypted` cast (Principle I, research R16).
Code identifiers are English; UI labels are Portuguese.

---

## Identity & access

### `users`

| Field | Type | Rules |
|-------|------|-------|
| name | string(120) | required |
| phone | string(20) | required, unique, normalised E.164 (`+258…`), login username |
| email | string(190) null | unique when present; enables self-service reset |
| password | string | hashed (bcrypt/argon2id), min 8 chars, not in breached list (`Password::uncompromised()`) |
| role | enum `coordinator`, `volunteer` | default `volunteer` |
| two_factor_secret / recovery_codes / confirmed_at | text null | Fortify; required for `coordinator` |
| last_login_at | timestamp null | |

- A coordinator may also have a volunteer profile (to be scheduled).
- Role changes are audited.

### `sessions`, `password_reset_tokens`

Laravel defaults (database session driver → revocable sessions).

---

## Volunteer profile

### `volunteers`

| Field | Type | Rules |
|-------|------|-------|
| user_id | FK users, unique | |
| status | enum | see state machine below |
| full_name | string(160) | required |
| birth_date 🔒 | date | required; age ≥ 14 at submission (FR-002a) |
| birth_year | smallint | derived, for filters |
| is_minor | bool | derived at submission and nightly |
| guardian_name 🔒 / guardian_phone 🔒 | string null | required if `is_minor` (age 14–17) |
| guardian_consent_confirmed_by / guardian_consent_confirmed_at | FK users / timestamp null | coordinator records guardian consent obtained offline; required to approve a minor (FR-002a) |
| guardian_consent_method | enum `in_person`, `phone` null | |
| neighbourhood 🔒 | string null | |
| education_level 🔒 / training 🔒 | string null | Form section on education |
| commitments 🔒 | text null | other church/work commitments |
| motivations 🔒 / comments 🔒 | text null | |
| max_frequency | enum `weekly`, `two_three_monthly`, `monthly`, `occasional` | required |
| variable_hours | bool | treated as available in every weekday × period, with a scoring penalty |
| available_for_training | bool | |
| can_support_others | bool | used for beginner pairing |
| consent_accepted_at | timestamp | required (FR-002) |
| consent_version | int | `consent_versions.version` accepted; if < latest version, middleware forces re-acceptance on next request (FR-002) |
| reviewed_by | FK users null | |
| reviewed_at | timestamp null | |
| previously_rejected_at | timestamp null | copied from `rejected_registrations` match on submit |
| review_notes | text null | correction request / rejection reason |
| inactive_since | date null | |
| inactive_until | date null | optional return date; scheduled job resumes to `active` (FR-005a) |
| paused_by | FK users null | volunteer themself or coordinator |
| removal_requested_at | timestamp null | set on request; cleared if volunteer cancels |
| removal_scheduled_for | timestamp null | requested_at + 7 days; scheduled job anonymises (FR-028) |
| anonymized_at | timestamp null | |

**Frequency → monthly cap** (spec assumptions): `weekly` → max 1 per ISO week;
`two_three_monthly` → 3; `monthly` → 1; `occasional` → 0 for auto-generation (manual only).

**State machine** (`status`):

```text
pending ──approve──▶ active ──pause──▶ inactive ──resume──▶ active
   │  └─request correction─▶ needs_correction ──resubmit──▶ pending
   └─reject──▶ rejected ──reverse (≤ 30 days)──▶ pending
                  └─(30 days, scheduled job)──▶ purged: volunteer + user rows deleted,
                                                 rejected_registrations row kept
any ──removal request──▶ anonymized   (terminal)
```

- Only `active` volunteers are eligible for scheduling.
- `pause` (volunteer or coordinator): published assignments from `inactive_since` to
  `inactive_until` (or all future ones) → `needs_replacement` + coordinators notified.
  `resume` happens manually or automatically on `inactive_until`. Never automatic inactivation.
- Minor (`is_minor`) cannot move to `active` without guardian fields filled.
- `anonymized`: personal fields nulled/replaced, `user` deleted, `full_name` = "Voluntário
  #<id>"; assignments retained (FR-028).

### `rejected_registrations`

| Field | Type | Rules |
|-------|------|-------|
| phone_hash | char(64) | HMAC-SHA256 of normalised phone with app key; indexed |
| rejected_at | timestamp | |

No other personal data. On a new registration whose phone hash matches, the registration is
accepted normally and flagged to coordinators ("cadastro anterior rejeitado em <data>") —
never blocked (FR-003a).

### `team_roles` (catalog of functions — "Funções")

| Field | Type | Rules |
|-------|------|-------|
| name | string(80) | unique |
| slug | string(80) | unique |
| description | text null | |
| is_active | bool | default true |
| sort_order | smallint | |

Seeded with: Operação de câmara, Realização e mistura de vídeo, Relações com o público,
Informática, Electrónica, Cablagem, Iluminação, Áudio, Transporte de equipamentos,
Montagem/desmontagem, Streaming (FR-007).

### `volunteer_skills`

| Field | Type | Rules |
|-------|------|-------|
| volunteer_id | FK | |
| team_role_id | FK | |
| interested | bool | |
| has_experience | bool | |
| level | enum `beginner`, `intermediate`, `advanced`, `mentor` | per role (R2) |
| is_preferred | bool | "área de maior interesse"; at most one or two per volunteer |

Unique (`volunteer_id`, `team_role_id`). Level ordering: beginner < intermediate < advanced < mentor.

### `availabilities`

| Field | Type | Rules |
|-------|------|-------|
| volunteer_id | FK | |
| weekday | tinyint 1–7 (ISO) | |
| period | enum `morning`, `afternoon`, `evening` | |

Unique (`volunteer_id`, `weekday`, `period`). Changes apply to unpublished schedules; for
published ones the system lists conflicts (spec edge case).

### `absences`

| Field | Type | Rules |
|-------|------|-------|
| volunteer_id | FK | |
| starts_on / ends_on | date | `ends_on >= starts_on`, not in the past |
| reason 🔒 | string null | optional |
| created_by | FK users | |

On create: overlapping published assignments → `needs_replacement` + coordinator notified.

---

## Activities & events

### `activity_templates` ("ModeloActividade")

| Field | Type | Rules |
|-------|------|-------|
| name | string(120) | required |
| weekday | tinyint 1–7 | |
| interval_weeks | tinyint | default 1 |
| starts_on | date | reference week for `interval_weeks` > 1; no occurrences before it |
| period | enum `morning`, `afternoon`, `evening` | |
| arrival_time / start_time / end_time | time | arrival ≤ start < end |
| teardown_minutes | smallint | default 0 |
| location | string(160) | |
| is_active | bool | |

### `template_positions`

`activity_template_id`, `team_role_id`, `quantity` (1–20), `min_level` (enum, nullable = any).

### `events`

| Field | Type | Rules |
|-------|------|-------|
| activity_template_id | FK null | null = special event |
| name | string(120) | |
| type | enum `recurring`, `special` | |
| date | date | |
| period | enum | derived from start time if not given |
| arrival_at / starts_at / ends_at | datetime | arrival ≤ start < end |
| teardown_until | datetime | ≥ ends_at; used for overlap checks |
| location | string(160) | |
| status | enum `scheduled`, `cancelled` | |
| is_customized | bool | true when edited away from template (FR-009) |
| cancelled_at / cancel_reason | null | |

Unique (`activity_template_id`, `date`). Generated by a scheduled job for the configured
horizon (default 3 months); customised occurrences are never overwritten.

### `positions`

`event_id`, `team_role_id`, `quantity`, `min_level` (nullable). Copied from template on
generation; editable per event.

---

## Schedules

### `schedules` ("Escala")

| Field | Type | Rules |
|-------|------|-------|
| starts_on / ends_on | date | no overlapping published schedules |
| status | enum `draft`, `published` | |
| created_by / published_by | FK users | |
| published_at | timestamp null | |
| generation_report | json null | gaps, warnings, suggestions from last run |

```text
draft ──publish──▶ published   (edits after publish are allowed, audited and notified)
draft ──regenerate──▶ draft    (only unlocked/auto assignments replaced)
```

### `assignments` ("Atribuição")

| Field | Type | Rules |
|-------|------|-------|
| schedule_id | FK | |
| position_id | FK | |
| event_id | FK | denormalised for constraints |
| volunteer_id | FK | |
| status | enum | see below |
| source | enum `auto`, `manual`, `swap` | |
| is_locked | bool | manual edits survive regeneration |
| override_note | string null | required when coordinator overrides a hard constraint |
| responded_at | timestamp null | |
| conflict_flagged_at | timestamp null | set when a later availability change clashes with this published shift; the shift is kept (spec edge case) |
| lock_version | int | optimistic concurrency; incremented on every update; stale writes rejected (FR-013) |
| updated_by | FK users null | shown in the conflict message |

Unique (`event_id`, `volunteer_id`) — one position per event (FR-011). Overlap across events
and other hard constraints enforced by `ConstraintChecker`.

```text
(publish) ──▶ confirmed            # default; no volunteer action required
confirmed ──decline──▶ declined ──(auto)──▶ swap_request(open) + needs_replacement
confirmed ──absence overlaps──▶ needs_replacement
needs_replacement ──substitute assigned──▶ (new assignment confirmed; old kept as history)
```

### `swap_requests` ("PedidoTroca")

| Field | Type | Rules |
|-------|------|-------|
| assignment_id | FK | |
| requester_id | FK volunteers | owner of assignment |
| target_volunteer_id | FK null | null = open request |
| accepted_by_id | FK volunteers null | must be eligible at accept time |
| status | enum `open`, `accepted`, `approved`, `rejected`, `cancelled`, `expired` | |
| is_urgent | bool | event starts < urgent threshold (default 48 h) |
| decided_by | FK users null | null when auto-approved |
| decided_at | timestamp null | |

```text
open ──eligible colleague accepts──▶ accepted ──coordinator approves / auto-approve*──▶ approved
open ──requester cancels──▶ cancelled        accepted ──coordinator rejects──▶ rejected
open ──event starts──▶ expired
* auto-approve when setting enabled and substitute level ≥ original requirement (FR-018)
```

On `approved`: original assignment → history, new assignment (`source = swap`) for acceptor;
both notified.

---

## Notifications

- `notifications` — Laravel database notifications (in-app centre).
- `push_subscriptions` — from `laravel-notification-channels/webpush` (endpoint, keys,
  user, user agent label).
- `notification_preferences` — `user_id`, `type` (enum of notification classes), `push`
  bool, `in_app` bool; defaults all on.

## Settings & audit

- `settings` — key/value: `event_horizon_months` (3), `check_reminder_hours_before` (72),
  `reminder_hours_before` (24),
  `urgent_threshold_hours` (48), `swap_auto_approve` (false),
  `registration_open` (true), `registration_closes_at` (nullable datetime).
- `activity_log` — `spatie/laravel-activitylog`; strictly append-only. Volunteer subjects are
  stored as `subject_type/subject_id` only; `properties` and descriptions MUST NOT contain
  volunteer names or personal values (🔒 fields always excluded). The audit page resolves
  display names at read time (anonymised → "Voluntário #id") (FR-029).
- `consent_versions` — `version` (int, unique, incrementing), `body` (text, declaration in
  Portuguese), `published_by` (FK users), `published_at`. The current version is the latest
  row (FR-002).

## Relationships (summary)

```text
User 1─0..1 Volunteer 1─* VolunteerSkill *─1 TeamRole
Volunteer 1─* Availability, 1─* Absence, 1─* Assignment
ActivityTemplate 1─* TemplatePosition, 1─* Event
Event 1─* Position 1─* Assignment *─1 Schedule
Assignment 1─* SwapRequest
User 1─* PushSubscription, 1─* NotificationPreference
```
