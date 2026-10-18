# Research: Cue App (001-cue-app)

**Date**: 2026-10-18 | **Plan**: [plan.md](plan.md) | **Spec**: [spec.md](spec.md)

Each entry records **Decision / Rationale / Alternatives considered**. Items marked
*(confirmed 2026-10-18)* resolved a `NEEDS CLARIFICATION` in the spec and were confirmed in
the clarification session recorded in [spec.md](spec.md#clarifications).

---

## 1. Spec clarifications

### R1 — Authentication method (FR-006) *(confirmed 2026-10-18)*

- **Decision**: Phone number + password as the primary login (phone is already the unique
  identity used for duplicate detection). E-mail is optional on the profile and enables
  self-service password reset; volunteers without e-mail get a one-time reset link generated
  by a coordinator and shared in person. Coordinators MUST enable TOTP two-factor
  authentication. Sessions are long-lived ("remember me" on by default on personal devices)
  and revocable from the profile page.
- **Rationale**: SMS OTP needs a paid SMS gateway, and SMS is explicitly out of scope this
  phase. Phone + password needs no external provider, is familiar to volunteers, and keeps
  friction low (Principle III) while 2FA protects the accounts that can see sensitive data
  (Principle I/II).
- **Alternatives considered**: SMS OTP (cost, provider dependency, out of scope); e-mail +
  password only (many volunteers rely on phone, not e-mail); magic links (need e-mail);
  passkeys (good future addition via Fortify/WebAuthn, but device support across volunteers
  is uneven — revisit in a later phase).

### R2 — Skill level per role vs. global (FR-004) *(confirmed 2026-10-18)*

- **Decision**: Store the level **per role** (`volunteer_skills.level`). On registration the
  form asks for one overall level (matching the paper form) and applies it to every role the
  volunteer marks; coordinators then adjust per role during approval or later.
- **Rationale**: Position requirements are per role (e.g., Direction ≥ Intermediate), so
  scheduling needs per-role levels to satisfy FR-011 correctly. Pre-filling keeps the
  registration as short as the paper form (SC-001).
- **Alternatives considered**: global level only (cannot express "advanced camera, beginner
  audio"; would force wrong assignments).

### R3 — Schedule visibility for volunteers (FR-027) *(confirmed 2026-10-18)*

- **Decision**: Volunteers see the **full published team schedule** showing only colleague
  first name + last initial, role and event. Their own shifts are highlighted. No contact
  details or profile data are exposed.
- **Rationale**: Seeing the team helps volunteers coordinate and find swap partners (US4);
  limiting fields satisfies Principle I.
- **Alternatives considered**: own shifts only (hurts swaps and team awareness); full names
  + phones (unnecessary exposure).

---

## 2. Stack (user-selected, best practices applied)

### R4 — Backend: Laravel 13

- **Decision**: Laravel 13 on PHP 8.3+ (8.4 recommended in production), scaffolded from the official **Laravel React starter
  kit** (Inertia + React + TypeScript + Tailwind + shadcn/ui pre-wired).
- **Rationale**: The starter kit ships the requested stack already integrated and kept current
  by the Laravel team; less glue code to maintain (Principle VI).
- **Best practices**: Form Requests for all validation; Policies for every model; Actions /
  service classes for business logic (scheduling, swaps); `declare(strict_types=1)`;
  Larastan level 8; Laravel Pint for formatting.

### R5 — Frontend: React 19 + Inertia.js + TypeScript

- **Decision**: Inertia.js (version bundled with the starter kit) with React 19, TypeScript
  strict mode, Vite. **Laravel Wayfinder** for typed route/controller helpers in TS.
- **Rationale**: Inertia gives server-side routing and session auth with SPA feel, no separate
  API to secure. Wayfinder removes hard-coded URLs.
- **Best practices**: Page data comes from Inertia props; use Inertia `<Form>`/`useForm` for
  mutations so Laravel validation errors map to fields automatically (Principle III).
  Partial reloads and deferred props for heavy coordinator pages.

### R6 — TanStack (Query + Table)

- **Decision**:
  - **TanStack Table** for coordinator data grids (volunteers, pending registrations,
    assignments, participation history) — sorting, filtering, column visibility, rendered with
    shadcn `Table`; on mobile, rows collapse into cards.
  - **TanStack Query** only for data that is *not* page props: interactive lookups inside a
    page (substitute suggestions, eligible swap partners, coverage polling, notification
    badge). These call small JSON endpoints under `/app-api/*` on the same session.
- **Rationale**: Avoids duplicating Inertia's job; uses Query where caching, background
  refetch and optimistic UI genuinely help.
- **Alternatives considered**: TanStack Query for everything (fights Inertia, doubles API
  surface); plain `fetch` (no caching/retry).

### R7 — UI: shadcn/ui + Tailwind CSS 4

- **Decision**: shadcn/ui components (Radix primitives) with Tailwind 4; `lucide-react`
  icons; `sonner` toasts; `Drawer` (vaul) for mobile sheets; `date-fns` with `pt` locale for
  formatting.
- **Rationale**: Accessible primitives (keyboard, ARIA) out of the box support WCAG 2.1 AA;
  components live in the repo and can be adapted.
- **Best practices**: Minimum 44×44 px touch targets; bottom navigation for volunteers on
  mobile; colour tokens checked for AA contrast in light and dark modes.

### R8 — Auth plumbing: Fortify + Sanctum

- **Decision**: **Laravel Fortify** (as in the starter kit) for login, password reset, 2FA,
  password confirmation. **Laravel Sanctum** in stateful SPA mode protects the `/app-api/*`
  JSON endpoints used by TanStack Query with the same session cookie, and is ready to issue
  scoped tokens if a native app or integration is added later.
- **Rationale**: Fortify supplies the flows; Sanctum gives one consistent guard for JSON
  requests without exposing tokens to JavaScript.
- **Config**: `username` = phone (normalized to E.164, `+258…`); login throttled 5/min per
  phone+IP; session cookie `Secure`, `HttpOnly`, `SameSite=Lax`; session lifetime 30 days for
  "remember me", 2 h idle for coordinators without it; sessions stored in database for
  revocation.

### R9 — Database: MySQL 8.4 LTS

- **Decision**: MySQL 8.4 LTS, InnoDB, `utf8mb4_unicode_ci`. Timestamps stored in UTC,
  displayed in `Africa/Maputo` (UTC+2, no DST).
- **Best practices**: Foreign keys with explicit `onDelete`; unique indexes enforcing
  business rules where possible (e.g., one assignment per volunteer per event); MySQL also
  backs queue, cache and sessions to keep one datastore (Principle VI).

### R10 — Testing: Pest + Vitest

- **Decision**:
  - **Pest 4** for unit + feature tests; **Pest browser testing** (Playwright) for a few
    end-to-end smoke flows on a mobile viewport (registration, confirm shift, publish).
  - **Vitest** + React Testing Library + `@testing-library/user-event` + `jsdom` for
    components and hooks; `vitest-axe` for automated accessibility checks.
- **Required coverage** (constitution VI): authorization matrix (role × resource), scheduling
  hard constraints, state machines, registration/consent.

---

## 3. Additions recommended for security, ease of use, PWA and push

### R11 — PWA

- **Decision**: `vite-plugin-pwa` in `injectManifest` mode with a custom TypeScript service
  worker (`resources/js/sw.ts`) using Workbox for precaching the app shell and handling
  `push` / `notificationclick`. Web App Manifest in Portuguese with maskable icons.
- **Rationale**: Installation is required for push on iOS (16.4+, home-screen apps only);
  custom SW needed for push handling.
- **Offline**: show a cached "Sem ligação" page and last-loaded own shifts (read-only); no
  offline writes (keeps consistency simple).

### R12 — Web Push

- **Decision**: `laravel-notification-channels/webpush` with VAPID keys; subscriptions stored
  per device in `push_subscriptions`. All notifications are Laravel Notification classes
  (`database` + `webpush` channels) dispatched on the queue.
- **Best practices**: Payload limited to title, short body, URL (Principle V — no sensitive
  data); delete subscriptions on 404/410 responses; retry with backoff; in-app notification
  centre from the `database` channel as fallback when push is disabled.
- **Alternatives considered**: Firebase Cloud Messaging (extra vendor and SDK; Web Push
  standard is sufficient), OneSignal (third-party data processor).

### R13 — Queues and scheduled jobs

- **Decision**: `database` queue driver, one Supervisor-managed worker; Laravel Scheduler
  (cron every minute) for: shift reminders (24 h, configurable), recurring event generation
  horizon roll-forward, expiry of stale swap requests, pruning old notifications.
- **Rationale**: Volume is small (≈100 volunteers); Redis/Horizon would add a service with no
  benefit now (Principle VI). Switching to Redis later is a config change.

### R14 — Scheduling engine

- **Decision**: A pure PHP domain service (`App\Domain\Scheduling\ScheduleGenerator`) that:
  1. Builds the candidate set per position by applying **hard constraints** as filters
     (availability day×period, absences, min level, monthly frequency cap, one position per
     event, no time overlap including setup/teardown, status Active, "Occasionally" excluded).
  2. Orders positions by scarcity (fewest candidates first).
  3. Scores candidates by the FR-012 priorities: fewest recent assignments → preferred role
     → "variable hours" penalty; tie-break by stable volunteer ID → **deterministic**.
  4. Pairs beginners with a "can support others" volunteer in the same role/event when
     possible.
  5. Emits gaps with "closest eligible" suggestions (which constraint failed).
- **Rationale**: Greedy + scarcity ordering is predictable, explainable (Principle IV) and
  fast enough for this scale; the hard-constraint checker is a separate class reused by
  manual edits and swaps so rules are defined once.
- **Alternatives considered**: ILP/constraint solver (opaque, extra dependency, overkill for
  ~100 volunteers × ~15 events/month).

### R15 — Audit log

- **Decision**: `spatie/laravel-activitylog` on Volunteer, Event, Schedule, Assignment,
  SwapRequest, Absence and role changes; logs causer, subject ID, the names of changed
  fields and before/after values for non-personal fields only (`full_name`, `name`, `phone`,
  `email`, guardian fields and all 🔒 fields are logged as "alterado" without values),
  timestamp. Rows are never updated or deleted by the app.

### R16 — Personal data protection

- **Decision**: Laravel `encrypted` casts for sensitive free-text and identity fields (date of
  birth, education, commitments, motivations, comments, guardian contact). Store a derived
  `is_minor` flag and `birth_year` for filtering without decrypting. Anonymization action for
  removal requests replaces personal fields and keeps assignment history linked to an
  anonymous ID.
- **Rationale**: Limits impact of a database leak (Principle I).

### R17 — Abuse protection for the public form

- **Decision**: Route rate limiting (e.g., 5 submissions/hour per IP), `spatie/laravel-honeypot`,
  and **Cloudflare Turnstile** (invisible, privacy-friendly) on the registration form; unique
  normalized phone number at database level.
- **Alternatives considered**: reCAPTCHA (heavier, privacy concerns), honeypot only (weak
  against targeted bots).

### R18 — HTTP security headers

- **Decision**: Middleware setting CSP with nonces (Vite supports `Vite::useCspNonce()`), HSTS,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` minimal. Option: `spatie/laravel-csp` to manage policy.

### R19 — PDF / print

- **Decision**: Print-optimised Inertia page (CSS `@media print`) plus PDF via
  `barryvdh/laravel-dompdf` from a Blade view.
- **Rationale**: dompdf has no headless Chrome dependency; schedule tables are simple.

### R20 — Localization and phone numbers

- **Decision**: Portuguese-only UI (strings written in Portuguese, Laravel validation messages
  from `laravel-lang/lang` `pt`). Phone numbers parsed/normalised with
  `propaganistas/laravel-phone` (default region MZ).
- **Rationale**: Single locale — no i18n framework needed now (YAGNI).

### R21 — Backups and operations

- **Decision**: `spatie/laravel-backup` nightly, encrypted archive to off-site S3-compatible
  storage, retention 30 days, monthly restore test. Error tracking with self-hosted or
  privacy-respecting service (e.g., Sentry with PII scrubbing) — optional.
- **Deployment**: Ubuntu VPS, Nginx, PHP-FPM 8.4, MySQL 8.4, Supervisor (queue), cron
  (scheduler), Let's Encrypt TLS. Provisioning via Laravel Forge or Laravel Cloud is
  acceptable.

### R22 — Code quality tooling

- **Decision**: Pint, Larastan (level 8), `tsc --noEmit`, `composer audit` and `npm audit`
  in CI. Frontend lint/format/test use **Vite+** (`vp`), which the Laravel 13 React starter
  kit ships: oxlint with the `jsx-a11y` plugin (warnings fail), oxfmt with Tailwind class
  sorting, and Vitest run through `vp test`. `vite` is aliased to
  `@voidzero-dev/vite-plus-core` and `vitest` pinned via npm `overrides`, as Vite+ requires.
- **Change from original plan (2026-10-18)**: ESLint + Prettier replaced by Vite+'s
  built-in oxlint/oxfmt, since the starter kit is built around them; same rules intent
  (TypeScript, React, accessibility), one tool instead of three.
- **Composer audit**: fails on vulnerabilities; abandoned packages are reported only
  (`audit.abandoned = report`), because the only ones are dev-only dependencies of
  `laravel-lang`.
