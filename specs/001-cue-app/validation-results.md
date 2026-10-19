# Validation Results: Cue App (001-cue-app)

Run on **2026-10-19** on branch `001-cue-app`, on a development Mac (SQLite, `php artisan serve`,
production frontend build). Scenarios refer to [quickstart.md](quickstart.md).

**Status**: automated checks and server-side measurements are done. Two success criteria
need people and real phones: sign-up time (SC-001) and push latency to a phone (SC-007).
They are marked **pending** below, so T135 stays open until they are recorded.

## Automated checks

| Check | Result |
|-------|--------|
| `php artisan test --testsuite=Unit,Feature` | ✅ 285 passed |
| `php artisan test --testsuite=Browser` (Pest + Playwright, phone viewport) | ✅ 3 passed: registration, decline shift, publish schedule |
| `npm run test` (Vitest + vitest-axe) | ✅ 28 passed |
| `npm run types`, `npm run check` (oxlint + jsx-a11y, oxfmt) | ✅ clean |
| `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (level 8) | ✅ clean |
| `composer audit` | ✅ no advisories; 2 abandoned dev-only packages (`dragon-code/pretty-array`, `dragon-code/support`, from `laravel-lang`) |
| `npm audit --omit=dev` | ✅ 0 vulnerabilities |

## Validation scenarios

"Automated" means a test exercises exactly the expected behaviour. These ran as part of the
suites above.

| # | Scenario | Result | Evidence |
|---|----------|--------|----------|
| 1 | Declaration unchecked blocks submission, data kept | ✅ Automated | `SubmitRegistrationTest` (consent required); `create.test.tsx` (steps keep answers, draft without sensitive fields) |
| 2 | Submit → `/cadastro/enviado`, coordinators notified, status pending | ✅ Automated | `SubmitRegistrationTest`; browser `SmokeTest` "a volunteer registers on a phone" |
| 3 | Same phone again → duplicate message | ✅ Automated | `SubmitRegistrationTest` "a phone number that already has an account is blocked" |
| 4 | Coordinator approves → volunteer can log in and be scheduled | ✅ Automated | `ReviewRegistrationTest` |
| 5 | Recurring template → occurrences for the horizon | ✅ Automated | `Events/*` occurrence tests; `events:generate` |
| 6 | Editing one occurrence changes only that date, marked customised | ✅ Automated | `Events/*` "customised" tests |
| 7 | Generate a month: no hard-constraint violations, gaps reported | ✅ Automated + measured | `ScheduleGeneratorTest`, `ConstraintCheckerTest`; December run below: 39 of 39 places filled, none over quantity |
| 8 | Manual assignment of an unavailable volunteer | ✅ Automated (rule changed) | Since commit `d10d0f6` (constitution 1.2.0), a broken rule only **warns** and the note is optional; the edit is audited. `ScheduleEditingTest` "a manual edit that breaks a rule only warns". The quickstart wording ("requires override note") is outdated. |
| 9 | Publish shows count; volunteers notified | ✅ Automated · ⏳ device push pending | `ScheduleEditingTest` (each volunteer notified once); browser `SmokeTest` publish. Arrival on a phone: see SC-007 |
| 10 | Absence over a shift → "Por substituir", coordinator notified | ✅ Automated | `AbsenceAndDeclineTest` |
| 11 | Swap: eligible colleague accepts, coordinator approves; ineligible never see it | ✅ Automated | `Swaps/*` "offered only to eligible colleagues", "an ineligible colleague can not accept" |
| 12 | Published shift starts Confirmed; "Não posso ir" opens a swap and notifies | ✅ Automated | `AbsenceAndDeclineTest`; browser `SmokeTest` decline |
| 13 | Availability matrix and coverage | ✅ Automated | `Overview/OverviewTest` |
| 14 | Reminder push for a shift ≤ 24 h away | ✅ Automated · ⏳ device pending | `Notifications/*` reminder tests (`shifts:remind`). The service worker now installs (fix below), but a push received on a phone has not been checked |
| 15 | Volunteer gets 403 on coordinator routes and APIs | ✅ Automated | `Authorization/*` "volunteers get 403 on every coordinator-only route" |
| 16 | Team schedule props: short name + role only | ✅ Automated | `ScheduleVisibilityTest` "short names and roles only", PDF test |
| 17 | 6 wrong logins in a minute → throttled in Portuguese | ✅ Automated | `LoginSecurityTest` "login is limited to 5 attempts per minute" |
| 18 | Data removal: immediate "Por substituir", anonymised after 7 days, cancellable | ✅ Automated | `Privacy/DataLifecycleTest` |
| 19 | Reject, reverse, purge after 30 days, re-register flagged | ✅ Automated | `Privacy/DataLifecycleTest`; `SubmitRegistrationTest` "previously rejected and purged phone" |
| 20 | Concurrent edit refused with "Alterado por …" | ✅ Automated | `ScheduleEditingTest` "a stale edit is refused with the name of who changed it" |

## Success criteria and constitution measurements

| Measure | Target | Result |
|---------|--------|--------|
| **SC-001** sign-up time | < 10 min | ⏳ **Pending**: time 3 people filling `/cadastro` on their own phones. The form has 9 steps, and the browser test fills the required fields of all of them. |
| **SC-002** generate + publish a month | < 15 min | ✅ Server time on the local demo team (66 active volunteers, December 2026: 9 events, 39 places): generation **0.07 s**, publish **0.03 s** (30 volunteers notified, queue run inline). The rest is the coordinator's review time. |
| **SC-007** publish → push on a device | < 1 min | ⏳ **Pending**: needs the production server (HTTPS, VAPID keys, queue worker) and a phone with notifications on. Server side, publishing sends one queued notification per volunteer; delivery depends on the worker (`docs/deployment.md` §4). |
| **Constitution III** frequent volunteer actions | ≤ 3 taps | ✅ Counted from the screens, starting on Início: see next shift **0**; decline a shift **2** ("Não posso ir" → confirm); ask for a swap **2–3** ("Pedir troca" → optionally pick a colleague → send); take an offered swap **3** (Trocas → "Assumir turno" → confirm); register an absence **2 taps plus the dates** (Ausências → fill dates → Registar). |

## Lighthouse (mobile) and PWA (T133)

Lighthouse 12.8.2 with mobile emulation, against the production build served by
`php artisan serve`; `/minha-escala` was opened as the local volunteer.

| Page | Accessibility | Best practices | Performance¹ |
|------|---------------|----------------|--------------|
| `/` (volunteer home) | 100 | 100 | — |
| `/cadastro` | 100 | 100 | 65 |
| `/entrar` | 100 | 100 | 67 |
| `/minha-escala` | 100 (was 98) | 100 | 65 |

- Fixed during the run: heading order on "A minha escala" (an `h3` with no `h2` above it)
  and the logo link's accessible name now matches its visible text.
- ¹ Performance was measured without compression: `php artisan serve` doesn't gzip.
  Lighthouse's largest suggested saving was text compression (≈ 547 KiB). The Nginx config
  in `docs/deployment.md` now turns on gzip. Re-measure on staging.
- **PWA installable**: ✅ Chrome reports no installability errors for `/`, and the browser
  fires `beforeinstallprompt`, which drives the new "Instalar a app" button. Lighthouse 12 no
  longer has a PWA category, so this was checked with Chrome DevTools
  (`Page.getInstallabilityErrors`).
- **Fixed — the service worker never installed.** The precache list had
  `manifest.webmanifest` relative to the worker (`/manifest.webmanifest`, which returns 404),
  so the install failed and the browser dropped the worker. No device got push or the
  offline page. `resources/js/sw.ts` now precaches `/build/manifest.webmanifest`; after the
  fix the worker is active and caches 118 files.

## Still to do (T135)

- [ ] SC-001: time 3 real sign-ups on phones (aim for one older and one younger volunteer)
- [ ] SC-007: on staging, publish a schedule and time when the push arrives on Android and on an installed iPhone app
- [ ] Re-run Lighthouse performance on staging (gzip, real TLS)
- [ ] Staging restore drill (`docs/deployment.md` → Restore drills)
