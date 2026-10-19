---

description: "Task list for Cue App — Team Scheduling for RTVAO"
---

# Tasks: Cue App — Team Scheduling for RTVAO

**Input**: Design documents from `specs/001-cue-app/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included where the constitution (Principle VI) makes them mandatory: authorization
rules, scheduling hard constraints, swap/absence/assignment state transitions, and the
registration/consent flow. Other areas get no dedicated test tasks.

**Organization**: Tasks are grouped by user story so each story can be implemented, tested
and demoed independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story from spec.md (US1–US6)
- Paths follow the single Laravel app layout in plan.md (`app/`, `resources/js/`,
  `database/`, `routes/`, `tests/` at repository root)
- UI copy is Portuguese; code identifiers are English

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Scaffold the Laravel 13 + Inertia + React app and tooling

- [X] T001 Scaffold the Laravel 13 React starter kit into a temp dir (`laravel new cue-tmp --react --pest`) and move its contents into the repository root, keeping existing `.specify/`, `.claude/`, `specs/` and `README.md`
- [X] T002 Configure MySQL 8.4, `APP_TIMEZONE=UTC`, `APP_DISPLAY_TIMEZONE=Africa/Maputo`, `APP_LOCALE=pt`, database session/cache/queue drivers in `.env.example` and `config/app.php`
- [X] T003 [P] Install backend packages via Composer: `laravel/sanctum`, `laravel-notification-channels/webpush`, `spatie/laravel-activitylog`, `spatie/laravel-honeypot`, `spatie/laravel-backup`, `propaganistas/laravel-phone`, `barryvdh/laravel-dompdf`, `laravel-lang/lang` (dev), `larastan/larastan` (dev) — update `composer.json`
- [X] T004 [P] Install frontend packages via npm: `@tanstack/react-query`, `@tanstack/react-table`, `date-fns`, `sonner`, `vaul`, `vite-plugin-pwa`, `workbox-precaching`, `workbox-routing`, and dev deps `vitest`, `@testing-library/react`, `@testing-library/user-event`, `jsdom`, `vitest-axe`, `eslint-plugin-jsx-a11y` — update `package.json`
- [X] T005 [P] Configure Vitest (via Vite+ `vp test`) with jsdom and setup file in the `test` block of `vite.config.ts` and `resources/js/test/setup.ts`; add `test`, `types`, `lint` scripts to `package.json`
- [X] T006 [P] Configure Larastan level 8 in `phpstan.neon` and Pint preset in `pint.json`
- [X] T007 [P] Enable TypeScript strict mode and oxlint jsx-a11y rules in `tsconfig.json` and the `lint` block of `vite.config.ts`
- [X] T008 [P] Publish Portuguese validation/auth messages with `laravel-lang` into `lang/pt/` and set Fortify/Inertia error messages to Portuguese
- [X] T009 [P] Add CI workflow running Pint, Larastan, `php artisan test`, `npm run types`, `npm run lint`, `npm run test`, `composer audit`, `npm audit --omit=dev` in `.github/workflows/ci.yml`
- [X] T010 Update `composer.json` `dev` script to run `serve`, `queue:listen`, `schedule:work` and `vite` together

**Checkpoint**: `composer run dev` serves the starter kit; CI passes on an empty app.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Auth, roles, security baseline, shared enums/settings, layouts and notification
plumbing used by every story.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Enums, settings and catalog

- [X] T011 [P] Create enums `UserRole` (named to avoid confusion with `TeamRole`), `VolunteerStatus`, `Level` (with ordering helper), `Period`, `Frequency` (with `monthlyCap()` per data-model), `AssignmentStatus`, `SwapStatus`, `EventStatus`, `ScheduleStatus` in `app/Enums/`
- [X] T012 [P] Create `settings` migration, `Setting` model and `App\Support\Settings` typed accessor with defaults (event_horizon_months=3, check_reminder_hours_before=72, reminder_hours_before=24, urgent_threshold_hours=48, swap_auto_approve=false, registration_open=true, registration_closes_at=null) in `database/migrations/`, `app/Models/Setting.php`, `app/Support/Settings.php`
- [X] T013 [P] Create `team_roles` migration, `TeamRole` model and `TeamRoleSeeder` with the 11 roles from FR-007 in `database/migrations/`, `app/Models/TeamRole.php`, `database/seeders/TeamRoleSeeder.php`

### Identity, auth and authorization

- [X] T014 Modify `users` migration: add `phone` (unique), nullable unique `email`, `role` enum, `last_login_at`; update `app/Models/User.php` casts and `database/factories/UserFactory.php` with `coordinator()` / `volunteer()` states
- [X] T015 Configure Fortify to authenticate by normalised phone (`propaganistas/laravel-phone`, region MZ), enable password reset, 2FA and password confirmation in `config/fortify.php` and `app/Providers/FortifyServiceProvider.php`; override the password-reset response so known and unknown phone/e-mail get the same message ("Se existir uma conta com estes dados, receberá instruções.")
- [X] T016 Add login rate limiter (5/min per phone+IP) and `Password::defaults()` (min 8, uncompromised) in `app/Providers/AppServiceProvider.php`
- [X] T017 [P] Create `EnsureRole` middleware in `app/Http/Middleware/` (the mandatory-2FA middleware was removed on 2026-10-19: two-factor is optional) and register aliases in `bootstrap/app.php`
- [X] T018 [P] Configure Sanctum stateful SPA mode for same-origin `/app-api/*` with 60 req/min limiter in `config/sanctum.php`, `bootstrap/app.php` and `routes/app-api.php`
- [X] T019 Create Portuguese login, password reset and 2FA pages adapted from the starter kit (phone field, "lembrar-me" default on) in `resources/js/pages/auth/`
- [X] T020 [P] Create session management (list + revoke database sessions; changing password ends all other sessions — FR-032) controller and page in `app/Http/Controllers/Settings/SessionController.php` and `resources/js/pages/settings/sessions.tsx` (route `/perfil/sessoes`)

### Security baseline

- [X] T021 [P] Create `SecurityHeaders` middleware (CSP with Vite nonce, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy) and register globally in `app/Http/Middleware/SecurityHeaders.php` and `bootstrap/app.php`
- [X] T022 [P] Configure secure session cookies (`Secure`, `HttpOnly`, `SameSite=Lax`, 30-day remember, 2 h idle) in `config/session.php`
- [X] T023 [P] Install and configure `spatie/laravel-activitylog` (exclude encrypted fields, causer = auth user, sanitiser so `full_name`, `name`, `phone`, `email`, guardian fields and 🔒 values are never stored in `properties`/descriptions — changed fields recorded as "alterado" only, subjects by ID only) in `config/activitylog.php`, `app/Support/AuditSanitizer.php` and its migration
- [X] T024 [P] Create Portuguese error pages for 403/404/419/429/500 via Inertia in `resources/js/pages/errors/` and exception rendering in `bootstrap/app.php`

### Frontend shell

- [X] T025 [P] Create `VolunteerLayout` with bottom navigation (Início, Escala, Trocas, Perfil) and 44 px touch targets in `resources/js/layouts/volunteer-layout.tsx`
- [X] T026 [P] Create `CoordinatorLayout` with sidebar (desktop) / drawer (phone) navigation in `resources/js/layouts/coordinator-layout.tsx`
- [X] T027 [P] Create TanStack Query client provider and `apiFetch` helper (XSRF cookie, 401 → `/entrar`) in `resources/js/lib/query-client.ts` and `resources/js/app.tsx`
- [X] T028 [P] Create Portuguese date/time formatters fixed to Africa/Maputo and phone display helper in `resources/js/lib/format.ts`
- [X] T029 [P] Create reusable `ConfirmDialog` (states who is affected) and `DataTable` (TanStack Table + shadcn Table, card layout on phone) in `resources/js/components/app/`
- [X] T030 Share Inertia props (`auth.user` with role and volunteerStatus, `unreadNotifications`; flash uses Inertia 3's built-in `Inertia::flash`; `pushPrompt` moves to US6 T118 because it is per device) in `app/Http/Middleware/HandleInertiaRequests.php` and types in `resources/js/types/index.d.ts`

### Notification plumbing

- [X] T031 Create `notifications` table (Laravel database channel) and `notification_preferences` migration + model; base `AppNotification` class that filters channels by preferences and dispatches `afterCommit` on the queue in `app/Notifications/AppNotification.php`; scheduled daily prune of in-app notifications older than 90 days (FR-034) in `routes/console.php`
- [X] T032 [P] Create in-app notification centre endpoints (`unread-count`, list, mark read) in `app/Http/Controllers/AppApi/NotificationController.php` and `routes/app-api.php`, plus `useNotifications` hook and bell component in `resources/js/hooks/use-notifications.ts` and `resources/js/components/app/notification-bell.tsx`

### Foundational tests

- [X] T033 [P] Feature test: guests are redirected from every non-public route, volunteers get 403 on every `/gestao/*` and coordinator `/app-api/*` route (route-list driven) in `tests/Feature/Authorization/RouteAccessTest.php`
- [X] T034 [P] Feature test: login throttling, sessions revocable, identical reset response for known and unknown accounts in `tests/Feature/Auth/LoginSecurityTest.php`
- [X] T035 [P] Feature test: security headers present on HTML responses in `tests/Feature/SecurityHeadersTest.php`

**Checkpoint**: A seeded coordinator can log in with phone + password (2FA optional); volunteers are
blocked from coordinator routes; layouts render on phone and desktop.

---

## Phase 3: User Story 1 — Online volunteer registration (Priority: P1) 🎯 MVP

**Goal**: A volunteer submits the digital registration form with consent; a coordinator
approves, rejects or requests correction; approved volunteers get their personal area.

**Independent Test**: Quickstart scenarios 1–4 and 19 — submit full form, see it pending,
approve, log in as the volunteer.

### Tests for User Story 1 ⚠️ (write first, must fail)

- [X] T036 [P] [US1] Feature test: registration requires consent (version + timestamp stored), rejects under-14, requires guardian fields for 14–17, blocks duplicate active phone, accepts and flags a previously rejected-and-purged phone, closed link shows closed page in `tests/Feature/Registration/SubmitRegistrationTest.php`
- [X] T037 [P] [US1] Feature test: approve / reject / request correction transitions; minor cannot be approved without recorded guardian consent; rejection reversible within 30 days in `tests/Feature/Registration/ReviewRegistrationTest.php`
- [X] T038 [P] [US1] Feature test: sensitive fields visible only to coordinators and the owner (Inertia props inspected for other volunteers) in `tests/Feature/Authorization/SensitiveDataVisibilityTest.php`
- [X] T039 [P] [US1] Feature test: consent version change forces re-acceptance on next request without touching assignments in `tests/Feature/Registration/ConsentVersionTest.php`
- [X] T039a [P] [US1] Feature test: coordinator reset link works once and expires after 24 h; volunteers cannot issue reset links in `tests/Feature/Auth/CoordinatorResetLinkTest.php`

### Implementation for User Story 1

- [X] T040 [P] [US1] Create `volunteers` migration and `Volunteer` model with encrypted casts, derived `birth_year`/`is_minor`, guardian consent fields, status enum, review, pause and removal fields per data-model in `database/migrations/`, `app/Models/Volunteer.php`, `database/factories/VolunteerFactory.php`
- [X] T041 [P] [US1] Create `volunteer_skills` migration + `VolunteerSkill` model (unique volunteer×role, level enum) in `database/migrations/` and `app/Models/VolunteerSkill.php`
- [X] T042 [P] [US1] Create `availabilities` migration + `Availability` model (unique volunteer×weekday×period) in `database/migrations/` and `app/Models/Availability.php`
- [X] T043 [P] [US1] Create `rejected_registrations` migration + model storing HMAC phone hash and `rejected_at` in `database/migrations/` and `app/Models/RejectedRegistration.php`
- [X] T043a [P] [US1] Create `consent_versions` migration + `ConsentVersion` model and seed version 1 with the current declaration in `database/migrations/`, `app/Models/ConsentVersion.php`, `database/seeders/ConsentVersionSeeder.php`
- [X] T044 [US1] Create `VolunteerPolicy` (viewSensitive, update, review, pause) in `app/Policies/VolunteerPolicy.php`
- [X] T045 [US1] Create `StoreRegistrationRequest` (all sections I–VII, password + confirmation with `Password::defaults()`, consent accepted, age ≥ 14, guardian required for minors, phone normalisation, honeypot, Turnstile token) in `app/Http/Requests/StoreRegistrationRequest.php` and Turnstile rule in `app/Rules/Turnstile.php`
- [X] T046 [US1] Implement `SubmitRegistration` action: create user (role volunteer) + volunteer (pending), skills with the overall level copied to each chosen role, availability, consent version/timestamp, previously-rejected flag; notify coordinators in `app/Actions/Registration/SubmitRegistration.php`
- [X] T047 [US1] Implement `ApproveVolunteer`, `RejectVolunteer`, `RequestCorrection`, `ReverseRejection` actions with audit logging and minor guardian-consent guard in `app/Actions/Registration/`
- [X] T048 [US1] Create `RegistrationController` (create/closed/store/submitted) with throttle 5/h/IP and `registration_open`/`registration_closes_at` check in `app/Http/Controllers/Public/RegistrationController.php` and routes in `routes/web.php`
- [X] T049 [US1] Build multi-step registration page (sections I–VII, declaração, progress indicator, inline errors, draft of non-sensitive fields kept in sessionStorage and cleared on submit, declaration text from latest `consent_versions` row) and submitted/closed pages in `resources/js/pages/registration/create.tsx`, `submitted.tsx`, `closed.tsx`
- [X] T050 [P] [US1] Create `RegistrationSubmitted` and `RegistrationDecided` notifications in `app/Notifications/`
- [X] T051 [US1] Create coordinator registrations list (DataTable, minors and "cadastro anterior rejeitado" badges) and detail page with approve/reject/correction actions, per-role level adjustment and guardian-consent recording dialog; every view of the full (sensitive) profile is audited (FR-035) in `app/Http/Controllers/Coordinator/RegistrationController.php` and `resources/js/pages/coord/registrations/index.tsx`, `show.tsx`
- [X] T052 [US1] Create `EnsureVolunteerActive` middleware routing `pending`/`needs_correction`/`rejected` volunteers to `/estado`, plus status page allowing resubmission after correction and showing "não aprovado" for rejected (reason only if the coordinator wrote one) in `app/Http/Middleware/EnsureVolunteerActive.php` and `resources/js/pages/volunteer/status.tsx`
- [X] T053 [US1] Create `EnsureConsentCurrent` middleware and re-acceptance page in `app/Http/Middleware/EnsureConsentCurrent.php` and `resources/js/pages/volunteer/consent.tsx`
- [X] T054 [US1] Create volunteer profile edit page (contact, availability, frequency, skill interest); create `UpdateAvailability` action in `app/Actions/Volunteers/UpdateAvailability.php` that saves availability/frequency (conflict detection added in T081a) in `app/Http/Controllers/Volunteer/ProfileController.php` and `resources/js/pages/volunteer/profile/edit.tsx`
- [X] T055 [US1] Create coordinator volunteers list/edit (levels per role; pause/resume of status lands with US4 T105) in `app/Http/Controllers/Coordinator/VolunteerController.php` and `resources/js/pages/coord/volunteers/index.tsx`, `edit.tsx`
- [X] T055a [US1] Create coordinator action to generate a single-use password reset link for a volunteer (expires in 24 h, invalidated on use or re-issue, audited, shown once with a copy button) in `app/Actions/Auth/IssueResetLink.php`, `app/Http/Controllers/Coordinator/ResetLinkController.php` and a button on `resources/js/pages/coord/volunteers/edit.tsx`
- [X] T056 [US1] Add registration open/close toggle and closing date to coordinator settings page in `app/Http/Controllers/Coordinator/SettingsController.php` and `resources/js/pages/coord/settings.tsx`
- [X] T056a [US1] Create coordinator page to edit and publish a new declaration version (ConfirmDialog: "Todos os voluntários terão de aceitar novamente", audited) in `app/Http/Controllers/Coordinator/ConsentController.php`, `resources/js/pages/coord/consent.tsx`
- [X] T057 [US1] Create placeholder volunteer home page (welcome + empty state "Ainda não tem turnos") in `resources/js/pages/volunteer/home.tsx`
- [X] T058 [US1] Seed one coordinator and demo volunteers in `database/seeders/DatabaseSeeder.php`

**Checkpoint**: US1 is fully functional and demoable on its own.

---

## Phase 4: User Story 2 — Activity days and events (Priority: P1)

**Goal**: Coordinators manage the role catalog, recurring activity templates and special
events with positions; occurrences are generated for the horizon.

**Independent Test**: Quickstart scenarios 5–6 — create a weekly template and a special event,
see both in the calendar with positions; edit one occurrence only.

### Tests for User Story 2 ⚠️

- [X] T059 [P] [US2] Feature test: occurrence generation for the horizon, idempotent re-run, customised occurrences untouched, template edits propagate only to non-customised future events in `tests/Feature/Events/GenerateOccurrencesTest.php`
- [X] T060 [P] [US2] Feature test: event time validation (arrival ≤ start < end ≤ teardown) and cancel (status, reason, audit; notifying assigned volunteers is tested in US3 T073) in `tests/Feature/Events/EventLifecycleTest.php`

### Implementation for User Story 2

- [X] T061 [P] [US2] Create `activity_templates` + `template_positions` migrations and models in `database/migrations/`, `app/Models/ActivityTemplate.php`, `app/Models/TemplatePosition.php`
- [X] T062 [P] [US2] Create `events` + `positions` migrations and models (unique template×date, `is_customized`, `teardown_until`) in `database/migrations/`, `app/Models/Event.php`, `app/Models/Position.php`
- [X] T063 [P] [US2] Create `TeamRolePolicy`, `ActivityTemplatePolicy`, `EventPolicy` (coordinator only) in `app/Policies/`
- [X] T064 [US2] Implement `GenerateOccurrences` action and scheduled daily command honoring `event_horizon_months` in `app/Actions/Events/GenerateOccurrences.php` and `app/Console/Commands/GenerateOccurrencesCommand.php`, registered in `routes/console.php`
- [X] T065 [US2] Implement `CancelEvent` action (status cancelled, reason, audited) in `app/Actions/Events/CancelEvent.php`; flagging assignments and the `EventCancelled` notification move to US3 T081 because assignments do not exist yet
- [X] T066 [US2] Create team roles CRUD (controller, Form Requests, page) in `app/Http/Controllers/Coordinator/TeamRoleController.php` and `resources/js/pages/coord/team-roles/index.tsx`
- [X] T067 [US2] Create activity templates CRUD with positions editor in `app/Http/Controllers/Coordinator/ActivityTemplateController.php` and `resources/js/pages/coord/templates/`
- [X] T068 [US2] Create events calendar (month view desktop, agenda list on phone), special event form with arrival/start/end/teardown and per-occurrence position editing in `app/Http/Controllers/Coordinator/EventController.php` and `resources/js/pages/coord/events/`
- [X] T069 [US2] Add cancel-event ConfirmDialog listing affected volunteers in `resources/js/pages/coord/events/show.tsx`
- [X] T070 [US2] Seed "Culto de Domingo" and "Estudo Bíblico de Terça" templates in `database/seeders/ActivityTemplateSeeder.php`

**Checkpoint**: US1 and US2 work independently.

---

## Phase 5: User Story 3 — Automatic schedule generation (Priority: P1)

**Goal**: Coordinator picks a period, the system proposes a constraint-safe, fair schedule,
the coordinator adjusts and publishes; volunteers see it.

**Independent Test**: Quickstart scenarios 7–9 and 20 — with ≥10 volunteers and a month of
events, generate, edit (with override warning), publish, and see notifications.

### Tests for User Story 3 ⚠️

- [ ] T071 [P] [US3] Unit tests for `ConstraintChecker`: availability day×period, absences, min level per role, monthly/weekly frequency caps, `occasional` excluded, one position per event, overlap including teardown, inactive excluded in `tests/Unit/Domain/Scheduling/ConstraintCheckerTest.php`
- [ ] T072 [P] [US3] Unit tests for `ScheduleGenerator`: never violates hard constraints, deterministic output for same input, fairness (difference ≤ 1 among equal volunteers — SC-005), preferred-role priority, variable-hours penalty, beginner–mentor pairing, gaps reported with failing constraint, fill rate ≥ 95% on the demo seed when supply is sufficient (SC-004) in `tests/Unit/Domain/Scheduling/ScheduleGeneratorTest.php`
- [ ] T073 [P] [US3] Feature test: manual edit violating a constraint requires `override_note` and is audited; stale `lock_version` rejected with "Alterado por <nome>"; publish notifies assigned volunteers and assignments start `confirmed`; an availability change hitting a published shift flags it without removing it in `tests/Feature/Scheduling/ScheduleEditingTest.php`
- [ ] T074 [P] [US3] Feature test: volunteers see published schedules only, colleagues as "Nome A." + role, no contact data in props in `tests/Feature/Authorization/ScheduleVisibilityTest.php`

### Implementation for User Story 3

- [ ] T075 [P] [US3] Create `absences` migration + `Absence` model (encrypted reason) — needed by constraints — in `database/migrations/` and `app/Models/Absence.php`
- [ ] T076 [P] [US3] Create `schedules` + `assignments` migrations and models (unique event×volunteer, `source`, `is_locked`, `override_note`, `lock_version`, `updated_by`) in `database/migrations/`, `app/Models/Schedule.php`, `app/Models/Assignment.php`
- [ ] T077 [P] [US3] Create `SchedulePolicy` and `AssignmentPolicy` in `app/Policies/`
- [ ] T078 [US3] Implement `ConstraintChecker` (`variable_hours` volunteers count as available in every slot) returning typed violations (`availability`, `absence`, `min_level`, `frequency_cap`, `same_event`, `time_overlap`, `inactive`) in `app/Domain/Scheduling/ConstraintChecker.php` and `app/Domain/Scheduling/Violation.php`
- [ ] T079 [US3] Implement `CandidateScorer` (recent load, preferred role, variable-hours penalty, stable ID tie-break) in `app/Domain/Scheduling/CandidateScorer.php`
- [ ] T080 [US3] Implement `ScheduleGenerator` (scarcity ordering, greedy assignment, beginner–mentor pairing, keeps locked assignments) and `GapReport` with closest-eligible suggestions in `app/Domain/Scheduling/ScheduleGenerator.php` and `app/Domain/Scheduling/GapReport.php`
- [ ] T081 [US3] Implement `GenerateSchedule`, `AssignVolunteer` (constraint check, override note, optimistic lock, audit) and `PublishSchedule` (assignments → confirmed, `SchedulePublished` notification) actions in `app/Actions/Scheduling/`; extend `CancelEvent` to flag the event's assignments and send `EventCancelled` (from US2 T065); make `GenerateOccurrences` keep any occurrence that has assignments instead of removing it
- [ ] T081a [US3] Extend `UpdateAvailability` to detect published assignments now violating availability/frequency, flag them and notify coordinators (database channel) via `AvailabilityConflict` notification in `app/Actions/Volunteers/UpdateAvailability.php` and `app/Notifications/AvailabilityConflict.php`
- [ ] T082 [P] [US3] Create `SchedulePublished` and `AssignmentChanged` notifications in `app/Notifications/`
- [ ] T083 [US3] Create coordinator schedule controller (index, create period, generate, edit, publish) in `app/Http/Controllers/Coordinator/ScheduleController.php` and `AssignmentController.php`, routes in `routes/web.php`
- [ ] T084 [US3] Create `/app-api/assignments/check` and `/app-api/assignments/{assignment}/substitutes` endpoints in `app/Http/Controllers/AppApi/AssignmentController.php`
- [ ] T085 [US3] Build month grid schedule editor (tablet/desktop) with gap highlighting, violation warnings, override-note dialog and conflict toast in `resources/js/pages/coord/schedules/edit.tsx` and `resources/js/components/app/schedule-grid.tsx`
- [ ] T086 [US3] Build phone per-event schedule editor (event list → event detail with substitute picker using TanStack Query) in `resources/js/pages/coord/schedules/event-editor.tsx` (FR-030)
- [ ] T087 [US3] Add publish ConfirmDialog ("N voluntários serão notificados") in `resources/js/pages/coord/schedules/edit.tsx`
- [ ] T088 [US3] Create PDF export (dompdf Blade view, schedule data and short names only) and print stylesheet in `app/Http/Controllers/Coordinator/SchedulePdfController.php` and `resources/views/pdf/schedule.blade.php`
- [ ] T089 [US3] Build volunteer "Minha escala" and team schedule pages (own shifts highlighted, short names) and update home with next 3 shifts in `app/Http/Controllers/Volunteer/MyShiftsController.php`, `resources/js/pages/volunteer/shifts/index.tsx`, `resources/js/pages/schedules/show.tsx`, `resources/js/pages/volunteer/home.tsx`
- [ ] T090 [US3] Seed ≥10 volunteers with varied availability/levels for generation demos in `database/seeders/DemoVolunteerSeeder.php`

**Checkpoint**: MVP complete — registration, events and published schedules (US1–US3).

---

## Phase 6: User Story 4 — Absences, declines, pauses and swaps (Priority: P2)

**Goal**: Volunteers register absences, decline shifts, pause participation and swap shifts;
coordinators approve swaps and pick substitutes.

**Independent Test**: Quickstart scenarios 10–12 — absence marks shift "Por substituir",
open swap accepted by eligible colleague and approved, decline opens a swap.

### Tests for User Story 4 ⚠️

- [ ] T091 [P] [US4] Feature test: absence/decline/pause move overlapping published assignments to `needs_replacement` and notify coordinators; absence reason hidden from colleagues in `tests/Feature/Swaps/AbsenceAndDeclineTest.php`
- [ ] T092 [P] [US4] Feature test: swap state machine (open → accepted → approved/rejected, cancelled, expired), only eligible volunteers can see/accept, urgency flag under threshold, auto-approve when enabled and level ≥ requirement in `tests/Feature/Swaps/SwapRequestTest.php`
- [ ] T093 [P] [US4] Feature test: pause with return date auto-resumes; no automatic inactivation in `tests/Feature/Volunteers/PauseResumeTest.php`

### Implementation for User Story 4

- [ ] T094 [P] [US4] Create `swap_requests` migration + `SwapRequest` model and `SwapRequestPolicy` in `database/migrations/`, `app/Models/SwapRequest.php`, `app/Policies/SwapRequestPolicy.php`
- [ ] T095 [US4] Implement `RegisterAbsence`, `DeclineAssignment`, `PauseVolunteer`, `ResumeVolunteer` actions (mark `needs_replacement`, notify `ReplacementNeeded`) in `app/Actions/Swaps/` and `app/Actions/Volunteers/`
- [ ] T096 [US4] Implement `CreateSwap`, `AcceptSwap` (re-check eligibility via ConstraintChecker), `DecideSwap` (auto-approve rule), `CancelSwap` actions in `app/Actions/Swaps/`
- [ ] T097 [P] [US4] Create `SwapRequested`, `SwapAccepted`, `SwapDecided`, `ReplacementNeeded` notifications in `app/Notifications/`
- [ ] T098 [US4] Create scheduled commands `ExpireSwaps` and `ResumePausedVolunteers` in `app/Console/Commands/` and register in `routes/console.php`
- [ ] T099 [US4] Create volunteer absences pages (list, add date range with optional reason, delete) in `app/Http/Controllers/Volunteer/AbsenceController.php` and `resources/js/pages/volunteer/absences/`
- [ ] T100 [US4] Add one-tap "Não posso ir" decline with ConfirmDialog on home and shifts pages in `resources/js/components/app/decline-shift-button.tsx`
- [ ] T101 [US4] Create volunteer swaps pages (mine, open-to-me, create to colleague or open, accept, cancel) and `/app-api/swaps/{swap}/eligible` endpoint in `app/Http/Controllers/Volunteer/SwapController.php`, `app/Http/Controllers/AppApi/SwapController.php`, `resources/js/pages/volunteer/swaps/`
- [ ] T102 [US4] Add pause/resume (optional return date, affected shifts listed in confirmation) to profile in `app/Http/Controllers/Volunteer/PauseController.php` and `resources/js/pages/volunteer/profile/edit.tsx`
- [ ] T103 [US4] Create coordinator swaps page (urgent first, approve/reject) and substitutes picker for `needs_replacement` shifts in `app/Http/Controllers/Coordinator/SwapController.php` and `resources/js/pages/coord/swaps/index.tsx`
- [ ] T104 [US4] Add auto-approve, urgent-threshold, event horizon months and 72 h / 24 h reminder-hours settings to `resources/js/pages/coord/settings.tsx` and `app/Http/Controllers/Coordinator/SettingsController.php`
- [ ] T105 [US4] Add coordinator "sem turnos há 3 meses" filter and pause/resume actions to `resources/js/pages/coord/volunteers/index.tsx`

**Checkpoint**: US4 works on top of a published schedule.

---

## Phase 7: User Story 5 — Availability overview (Priority: P2)

**Goal**: Coordinators see availability by day × period × role/level, event coverage and
participation history.

**Independent Test**: Quickstart scenario 13 — open availability and coverage, identify
shortages without manual calculation.

### Implementation for User Story 5

- [ ] T106 [P] [US5] Implement `AvailabilityMatrix` and `CoverageReport` query services (shortage = demand > supply per role) in `app/Domain/Overview/AvailabilityMatrix.php` and `app/Domain/Overview/CoverageReport.php`
- [ ] T107 [US5] Create `/app-api/availability-matrix` and `/app-api/coverage` endpoints in `app/Http/Controllers/AppApi/OverviewController.php` and `routes/app-api.php`
- [ ] T108 [US5] Build availability matrix page with role/level filters and shortage badges in `resources/js/pages/coord/availability.tsx`
- [ ] T109 [US5] Build coverage page (next 30 days, filled/required, gaps) in `resources/js/pages/coord/coverage.tsx`
- [ ] T110 [US5] Build participation history page (shifts done, declines, swaps, real vs declared frequency) in `app/Http/Controllers/Coordinator/VolunteerHistoryController.php` and `resources/js/pages/coord/volunteers/history.tsx`
- [ ] T111 [US5] Build coordinator dashboard (pending approvals, urgent swaps, gaps next 30 days) in `app/Http/Controllers/Coordinator/DashboardController.php` and `resources/js/pages/coord/dashboard.tsx`

**Checkpoint**: US5 independently usable with seeded data.

---

## Phase 8: User Story 6 — Push notifications & PWA (Priority: P2)

**Goal**: Installable app; users opt in to push and receive schedule, reminder and request
notifications within 1 minute.

**Independent Test**: Quickstart scenarios 9 and 14 — enable notifications, receive publish
push and a reminder.

### Tests for User Story 6 ⚠️

- [ ] T112 [P] [US6] Feature test: notifications respect preferences, payloads contain no sensitive fields, 410 responses delete subscriptions, reminders (72 h check with decline link, 24 h) sent once per assignment in `tests/Feature/Notifications/PushDeliveryTest.php`

### Implementation for User Story 6

- [ ] T113 [US6] Configure `laravel-notification-channels/webpush` (VAPID keys, `push_subscriptions` migration, `HasPushSubscriptions` on User) in `config/webpush.php` and `app/Models/User.php`
- [ ] T114 [US6] Add `webpush` channel with minimal payload (title, body ≤ 120 chars, url, tag, TTL, urgency) to `app/Notifications/AppNotification.php`
- [ ] T115 [US6] Create push subscription endpoints (POST/DELETE `/app-api/push-subscriptions`) in `app/Http/Controllers/AppApi/PushSubscriptionController.php`
- [ ] T116 [US6] Configure `vite-plugin-pwa` (injectManifest, Portuguese manifest, maskable icons) in `vite.config.ts` and `public/icons/`
- [ ] T117 [US6] Write service worker (precache shell, offline "Sem ligação" page, cached own shifts read-only, `push` and `notificationclick` handlers) in `resources/js/sw.ts`
- [ ] T118 [US6] Create `usePushSubscription` hook and enable-notifications banner with iOS "Adicionar ao ecrã principal" instructions in `resources/js/hooks/use-push-subscription.ts` and `resources/js/components/app/push-prompt.tsx`
- [ ] T119 [US6] Create notification preferences page in `app/Http/Controllers/Volunteer/NotificationPreferenceController.php` and `resources/js/pages/volunteer/profile/notifications.tsx`
- [ ] T120 [US6] Implement `ShiftCheck` (72 h, "Não posso ir" action) and `ShiftReminder` (24 h) notifications and scheduled `SendShiftReminders` command in `app/Notifications/` and `app/Console/Commands/SendShiftReminders.php`
- [ ] T121 [US6] Add the `webpush` channel to the `AvailabilityConflict` notification (created in T081a) in `app/Notifications/AvailabilityConflict.php`

**Checkpoint**: All user stories functional.

---

## Phase 9: Privacy & Data Lifecycle (Cross-Cutting)

**Purpose**: Retention and anonymisation rules (FR-003a, FR-028, FR-029) spanning several
stories.

- [ ] T122 [P] Feature test: rejected registrations purged after 30 days leaving only phone hash; removal request schedules anonymisation in 7 days, cancellable, future shifts → `needs_replacement`; an audit row for a profile edit contains no name, phone or e-mail values; audit rows are unchanged after anonymisation and the audit page shows "Voluntário #id" while keeping coordinator names in `tests/Feature/Privacy/DataLifecycleTest.php`
- [ ] T123 Implement `PurgeRejectedRegistrations` scheduled command in `app/Console/Commands/PurgeRejectedRegistrations.php`
- [ ] T124 Implement `RequestDataRemoval` and `CancelDataRemoval` actions and `AnonymizeVolunteer` action (null 🔒 fields, delete user, rename to "Voluntário #id"; audit log untouched) in `app/Actions/Registration/`
- [ ] T125 Implement `ProcessScheduledRemovals` scheduled command in `app/Console/Commands/ProcessScheduledRemovals.php`
- [ ] T126 Add data removal request/cancel UI with password confirmation to `resources/js/pages/volunteer/profile/edit.tsx` and `app/Http/Controllers/Volunteer/DataRemovalController.php`
- [ ] T127 Create read-only audit log page (resolves subject names at read time; anonymised → "Voluntário #id") in `app/Http/Controllers/Coordinator/AuditController.php` and `resources/js/pages/coord/audit.tsx`

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T128 [P] Vitest + vitest-axe tests for `ConfirmDialog`, `DataTable`, registration form steps, `DeclineShiftButton` and `PushPrompt` in `resources/js/**/*.test.tsx`
- [ ] T129 [P] Pest browser smoke tests on a phone viewport: registration, decline shift, publish schedule in `tests/Browser/SmokeTest.php`
- [ ] T130 [P] Configure `spatie/laravel-backup` nightly encrypted off-site backup with 30-day retention in `config/backup.php` and `routes/console.php`; perform a restore drill on a staging database and document it in `docs/deployment.md`
- [ ] T131 [P] Add log scrubbing so passwords, tokens and 🔒 fields never reach logs in `config/logging.php` and `app/Logging/ScrubSensitiveData.php`
- [ ] T132 Review all Portuguese copy for clarity and consistent terms (turno, função, Por substituir) across `resources/js/pages/`
- [ ] T133 Run Lighthouse mobile on `/`, `/cadastro`, `/minha-escala` and fix issues until PWA installable and Accessibility ≥ 95
- [ ] T134 Write deployment notes (Nginx, PHP-FPM, Supervisor queue worker, cron scheduler, TLS, VAPID/Turnstile env) in `docs/deployment.md` and update `README.md` "Getting started"
- [ ] T135 Run every scenario in `specs/001-cue-app/quickstart.md` and record results, including measured sign-up time (< 10 min, SC-001), month schedule generate + publish time (< 15 min, SC-002), publish-to-device push latency (< 1 min, SC-007) and tap counts for frequent volunteer actions (≤ 3, constitution III), in `specs/001-cue-app/validation-results.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)** → no dependencies
- **Foundational (Phase 2)** → depends on Setup; blocks all stories
- **US1 (Phase 3)** → depends on Foundational
- **US2 (Phase 4)** → depends on Foundational (uses `team_roles` only); can run in parallel with US1
- **US3 (Phase 5)** → depends on US1 (volunteers, skills, availability) and US2 (events, positions)
- **US4 (Phase 6)** → depends on US3 (assignments, ConstraintChecker)
- **US5 (Phase 7)** → depends on US1 + US2; coverage/history views need US3 data
- **US6 (Phase 8)** → depends on Foundational notification plumbing; reminders need US3; can start in parallel with US4/US5
- **Privacy (Phase 9)** → depends on US1 and US3 (assignments)
- **Polish (Phase 10)** → after the desired stories are complete

### User Story Dependency Graph

```text
Setup → Foundational ─┬─ US1 ─┐
                      └─ US2 ─┴─ US3 ─┬─ US4
                                      ├─ US5
                                      ├─ US6 (push plumbing can start after Foundational)
                                      └─ Privacy → Polish
```

### Within Each User Story

- Tests marked ⚠️ are written first and must fail before implementation
- Migrations/models → policies → actions/domain services → controllers/routes → pages
- Commit after each task or logical group

### Parallel Opportunities

- Setup: T003–T009 in parallel after T001–T002
- Foundational: enums/settings/catalog (T011–T013), middleware (T017, T018, T021–T024), frontend shell (T025–T029) and tests (T033–T035) in parallel
- US1 and US2 can be built by two people at the same time
- After US3: US4, US5 and US6 can proceed in parallel

---

## Parallel Example: User Story 1

```bash
# Tests first (parallel):
T036 SubmitRegistrationTest   T037 ReviewRegistrationTest
T038 SensitiveDataVisibilityTest   T039 ConsentVersionTest

# Then models (parallel):
T040 Volunteer   T041 VolunteerSkill   T042 Availability   T043 RejectedRegistration   T043a ConsentVersion
```

## Parallel Example: User Story 3

```bash
# Tests (parallel): T071 ConstraintChecker  T072 ScheduleGenerator  T073 Editing  T074 Visibility
# Models (parallel): T075 Absence  T076 Schedule/Assignment  T077 Policies
```

---

## Implementation Strategy

### MVP First

1. Phase 1 Setup → Phase 2 Foundational
2. Phase 3 (US1) → **stop and validate**: real volunteers can register and be approved —
   usable on its own to replace the paper form
3. Phase 4 (US2) + Phase 5 (US3) → **MVP**: first generated and published schedule

### Incremental Delivery

1. Registration live (US1) — start collecting profiles immediately
2. Events + schedules (US2–US3) — first monthly schedule published
3. Swaps/absences (US4) and notifications (US6) — reduce no-shows (SC-008)
4. Overview (US5), privacy lifecycle and polish

---

## Notes

- [P] = different files, no dependency on incomplete tasks
- Every protected route MUST go through a Policy (constitution Principle I)
- Never log or push 🔒 fields (Principles I, V)
- Stop at each checkpoint to validate the story independently
