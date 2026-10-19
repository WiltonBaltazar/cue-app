# Cue App

Scheduling for the RTVAO media team (Rádio e Televisão Alfa & Ómega, IEAD – Ministério
Alfa & Ómega). Cue App replaces paper registration forms and scattered group messages with a
single place to register volunteers, plan events, generate fair schedules and handle swaps and
absences.

> **Status**: specification phase. No application code yet — see [Roadmap](#roadmap).

## Who it is for

| Role | What they do |
|------|--------------|
| **Coordinator** | Approves registrations, manages roles and events, generates and publishes schedules, approves swaps and absences. |
| **Volunteer** | Registers online, keeps availability up to date, views and confirms shifts, registers absences, requests swaps. |

## Key features

- **Online registration** — digital version of the volunteer registration form, with
  recorded consent and coordinator approval.
- **Activities and events** — recurring activities (e.g., Sunday Service, Tuesday Bible
  Study) and one-off events, each with arrival/setup time and required positions.
- **Automatic schedule generation** — respects availability, absences, skill level, maximum
  frequency and overlaps; balances workload and pairs beginners with mentors; reports gaps
  instead of breaking rules.
- **Absences and swaps** — volunteers register absences and request swaps; only eligible
  colleagues can accept; coordinators approve.
- **Availability overview** — day × period grid by role and level, event coverage and
  shortage alerts.
- **Push notifications** — published schedules, changes, reminders and requests, as an
  installable app (PWA) on Android and iOS.

Interface language: Portuguese. Time zone: Africa/Maputo.

## Guiding principles

The project is governed by the [constitution](.specify/memory/constitution.md). In short:

1. **Privacy & least-privilege access** — sensitive data is visible only to coordinators and
   the volunteer it belongs to; authorization is enforced server-side.
2. **Secure by default** — authentication, rate limiting, input validation, HTTPS and an audit
   log of every change to schedules, approvals and registrations.
3. **Effortless to use** — mobile-first, plain Portuguese, accessible (WCAG 2.1 AA); common
   volunteer actions within 3 taps.
4. **Schedule integrity** — hard constraints are never violated automatically; published
   shifts are never changed silently.
5. **Respectful notifications** — user-chosen preferences, no sensitive data in push payloads.
6. **Tested where it matters** — authorization, scheduling rules and workflows are covered by
   automated tests; everything else stays simple.

## Project structure

```text
.
├── specs/001-cue-app/           # Spec (Portuguese), plan, research, data model, contracts
├── .specify/
│   ├── memory/constitution.md   # Project principles and governance
│   ├── templates/               # Spec Kit templates (spec, plan, tasks, checklist)
│   └── scripts/                 # Spec Kit helper scripts
└── .claude/skills/              # Spec Kit commands for Claude Code
```

## Development workflow

This project uses [Spec Kit](https://github.com/github/spec-kit) for spec-driven development:

1. `/speckit-specify` — write or update the feature specification
2. `/speckit-clarify` — resolve open questions in the spec
3. `/speckit-plan` — create the technical plan (must pass the Constitution Check)
4. `/speckit-tasks` — break the plan into ordered tasks
5. `/speckit-implement` — implement the tasks

A change is done only when required tests pass, the UI has been checked on a phone-sized
screen, and the Portuguese copy has been reviewed. Changes touching authentication,
authorization, personal data or the scheduling engine require a review against the
constitution.

## Roadmap

- [x] Feature specification (`specs/001-cue-app/spec.md`)
- [x] Constitution v1.0.1
- [x] Technical plan (`specs/001-cue-app/plan.md`)
- [ ] Confirm the defaults chosen in research for the spec's open questions (`/speckit-clarify`):
  - login with phone + password, optional two-factor login
  - skill level stored per role
  - volunteers see the full published schedule (short names and roles only)
- [ ] Task breakdown and implementation

## Tech stack

Laravel 13 (PHP 8.4) · Inertia.js · React 19 + TypeScript · TanStack Query & Table ·
shadcn/ui + Tailwind CSS 4 · MySQL 8.4 · Fortify + Sanctum · Web Push (VAPID) + PWA ·
Pest + Vitest. Full rationale in
[research.md](specs/001-cue-app/research.md).

## Out of scope (current phase)

- Equipment inventory
- On-site QR code attendance
- Integration with the Ministry's multi-tenant platform
- WhatsApp/SMS notifications

## Getting started

The application code is not scaffolded yet. The planned setup and validation steps are in
[quickstart.md](specs/001-cue-app/quickstart.md).
