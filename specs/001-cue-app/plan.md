# Implementation Plan: Cue App — Team Scheduling for RTVAO

**Branch**: `001-cue-app` | **Date**: 2026-10-18 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-cue-app/spec.md`

## Summary

A mobile-first, installable web app (PWA) where volunteers register online, keep their
availability, see and confirm shifts, register absences and swap shifts, while coordinators
approve registrations, define recurring activities and special events, generate fair schedules
automatically and publish them with push notifications.

Technical approach: one Laravel 13 monolith with Inertia.js + React 19 (TypeScript) from the
official Laravel React starter kit, shadcn/ui components, MySQL 8.4 for data, queue, cache and
sessions; Fortify + Sanctum session auth; standard Web Push via VAPID; a deterministic,
constraint-first scheduling service in pure PHP. See [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13 minimum; 8.4 recommended in production),
TypeScript 5 (strict) with React 19

**Primary Dependencies**:
- Backend: Laravel 13, Inertia Laravel adapter, Laravel Fortify (login, reset, 2FA),
  Laravel Sanctum (stateful session for `/app-api`), Laravel Wayfinder,
  `laravel-notification-channels/webpush`, `spatie/laravel-activitylog`,
  `spatie/laravel-honeypot`, `spatie/laravel-backup`, `propaganistas/laravel-phone`,
  `barryvdh/laravel-dompdf`, `laravel-lang/lang` (pt), Cloudflare Turnstile validation
- Frontend: React 19, `@inertiajs/react`, `@tanstack/react-query`, `@tanstack/react-table`,
  shadcn/ui (Radix) + Tailwind CSS 4, `lucide-react`, `sonner`, `vaul`, `date-fns` (pt),
  `vite-plugin-pwa` (Workbox, injectManifest)

**Storage**: MySQL 8.4 LTS (app data, `database` queue, cache, sessions); encrypted casts for
sensitive fields; nightly encrypted off-site backups

**Testing**: Pest 4 (unit, feature, browser smoke via Playwright); Vitest + React Testing
Library + `vitest-axe` (run via Vite+ `vp test`); Larastan L8, Pint, oxlint (+ jsx-a11y)
and oxfmt via Vite+, `tsc --noEmit`

**Target Platform**: Linux VPS (Ubuntu, Nginx, PHP-FPM, Supervisor, cron, TLS). Clients:
mobile browsers (Android Chrome, iOS Safari 16.4+ installed PWA for push) and desktop browsers

**Project Type**: Web application (server-driven SPA via Inertia, installable PWA)

**Performance Goals**: Page interactions < 1 s p95 on 3G-class mobile; schedule generation
for one month < 10 s; notification delivery < 1 min (SC-007); registration < 10 min (SC-001)

**Constraints**: Portuguese UI; Africa/Maputo display time zone (UTC storage); WCAG 2.1 AA;
no sensitive data in push payloads; SMS/WhatsApp out of scope; offline = read-only cached
own shifts

**Scale/Scope**: ~100–200 volunteers, 1–5 coordinators, ~15–25 events/month, ~35 screens

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Pre-research | Post-design |
|-----------|------|--------------|-------------|
| I. Privacy & least-privilege | Policies on every model; sensitive fields restricted to coordinator + owner; consent timestamp; anonymisation; minors gated | ✅ planned | ✅ Policies per route in [routes.md](contracts/routes.md); 🔒 encrypted fields in [data-model.md](data-model.md); colleague data limited (R3); `anonymized` state |
| II. Secure by default | Auth everywhere except registration; throttled login; abuse protection; validation; CSRF; HTTPS + headers; secrets in env; audit log | ✅ planned | ✅ Fortify with optional 2FA (R1, R8), Turnstile + honeypot + throttle (R17), CSP/HSTS (R18), activitylog (R15), `composer/npm audit` (R22) |
| III. Effortless to use | Portuguese, mobile-first PWA, ≤ 3 taps, inline errors, confirmations, WCAG AA, low-friction security | ✅ planned | ✅ Shifts confirmed by default; home shows next shifts + one-tap decline; bottom nav; Inertia field errors; confirm dialogs on publish/cancel; vitest-axe + Lighthouse; phone + password with long sessions |
| IV. Schedule integrity | Hard constraints never broken by generator; overrides warned + audited; no silent changes; deterministic, explainable | ✅ planned | ✅ Single `ConstraintChecker` shared by generator, manual edits, swaps (R14); `override_note`; `generation_report` with gap reasons; `availability_conflict` notification |
| V. Notifications | Spec-defined types; preferences; minimal payload; retries/cleanup; in-app fallback | ✅ planned | ✅ [notifications.md](contracts/notifications.md) |
| VI. Tested & simple | Required tests for authz, constraints, state machines, registration; single app + single DB | ✅ planned | ✅ Quality gates in [quickstart.md](quickstart.md); monolith, MySQL-backed queue — no Redis/microservices |

**Result**: PASS — no violations; Complexity Tracking empty.

## Project Structure

### Documentation (this feature)

```text
specs/001-cue-app/
├── spec.md
├── plan.md              # This file
├── research.md          # Phase 0 decisions (R1–R22)
├── data-model.md        # Phase 1 entities, rules, state machines
├── quickstart.md        # Phase 1 run + validation guide
├── contracts/
│   ├── routes.md        # Web routes, Inertia pages, access rules
│   ├── app-api.md       # JSON endpoints for TanStack Query
│   └── notifications.md # Push/in-app notification types and payloads
└── tasks.md             # Phase 2 (/speckit-tasks — not created here)
```

### Source Code (repository root)

```text
app/
├── Actions/                     # single-purpose use cases
│   ├── Registration/            # SubmitRegistration, ApproveVolunteer, AnonymizeVolunteer
│   ├── Scheduling/              # GenerateSchedule, PublishSchedule, AssignVolunteer
│   └── Swaps/                   # CreateSwap, AcceptSwap, DecideSwap
├── Domain/Scheduling/           # ScheduleGenerator, ConstraintChecker, CandidateScorer, GapReport
├── Enums/                       # Role, VolunteerStatus, Level, Period, Frequency, AssignmentStatus…
├── Http/
│   ├── Controllers/{Public,Volunteer,Coordinator,AppApi}/
│   ├── Middleware/              # SecurityHeaders, EnsureRole, EnsureVolunteerActive
│   └── Requests/                # Form Requests per action
├── Models/
├── Notifications/               # one class per type in contracts/notifications.md
├── Policies/
└── Console/                     # scheduled: GenerateOccurrences, SendShiftReminders, ExpireSwaps

database/{migrations,factories,seeders}/
resources/
├── js/
│   ├── pages/{registration,volunteer,coord,schedules,auth,profile}/
│   ├── components/{ui,app}/     # ui = shadcn generated; app = domain components
│   ├── layouts/                 # VolunteerLayout (bottom nav), CoordinatorLayout (sidebar)
│   ├── hooks/                   # useNotifications, usePushSubscription, queries
│   ├── lib/                     # queryClient, formatters (pt, Africa/Maputo)
│   ├── sw.ts                    # service worker: precache, push, notificationclick
│   └── app.tsx
└── views/pdf/schedule.blade.php
routes/{web.php,app-api.php,console.php}
tests/
├── Unit/Domain/Scheduling/      # constraint + scoring tests
├── Feature/{Registration,Scheduling,Swaps,Authorization,Notifications}/
└── Browser/                     # Pest browser smoke flows
resources/js/**/*.test.tsx       # Vitest
```

**Structure Decision**: Single Laravel application (starter-kit layout). React lives in
`resources/js`; no separate frontend/backend projects, matching Principle VI.

## Complexity Tracking

No constitution violations — table intentionally empty.
