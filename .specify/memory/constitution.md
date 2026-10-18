<!--
Sync Impact Report
==================
Version change: 1.0.0 → 1.0.1
Bump rationale: PATCH — Principle III example list updated ("confirm/decline" → "decline a
shift") after shifts became confirmed by default (spec clarification 2026-10-18). No
semantic change to any principle.

Previous: (template, unversioned) → 1.0.0 — initial ratification.

Modified principles (template slot → new title):
  - [PRINCIPLE_1_NAME] → I. Privacy & Least-Privilege Access (NON-NEGOTIABLE)
  - [PRINCIPLE_2_NAME] → II. Secure by Default
  - [PRINCIPLE_3_NAME] → III. Effortless for Coordinators and Volunteers
  - [PRINCIPLE_4_NAME] → IV. Schedule Integrity & Transparency
  - [PRINCIPLE_5_NAME] → V. Respectful, Reliable Notifications
Added principles:
  - VI. Tested Where It Matters, Simple Everywhere Else
Added sections:
  - Security & Privacy Requirements (was [SECTION_2_NAME])
  - Development Workflow & Quality Gates (was [SECTION_3_NAME])
Removed sections: none

Templates requiring updates:
  - .specify/templates/plan-template.md — ✅ no change needed (Constitution Check gates are
    derived from this file at plan time)
  - .specify/templates/spec-template.md — ✅ no change needed
  - .specify/templates/tasks-template.md — ✅ no change needed (security, accessibility and
    test tasks are required by principles below and fit existing phases)
  - .claude/skills/speckit-*/SKILL.md — ✅ reviewed; no outdated references
  - README.md — ✅ created; summarizes principles I–VI

Follow-up TODOs:
  - spec.md FR-006 (authentication method) is still NEEDS CLARIFICATION; Principle II constrains
    whichever option is chosen.
-->

# Cue App Constitution

Cue App is the scheduling system for the RTVAO media team (Rádio e Televisão Alfa & Ómega,
IEAD – Ministério Alfa & Ómega). It serves two audiences: **coordinators**, who manage
volunteers, events and schedules, and **volunteers**, many of whom are non-technical and use
the app mainly on a phone. Every decision MUST keep the app secure for them and easy for them.

## Core Principles

### I. Privacy & Least-Privilege Access (NON-NEGOTIABLE)

- Every request MUST be authorized server-side against the user's role (Coordinator or
  Volunteer) and resource ownership; hiding UI elements is never an access control.
- Sensitive personal data (date of birth, education, commitments, motivations, guardian
  contacts for minors) MUST be readable only by coordinators and the volunteer it belongs to.
- Volunteers MUST only see colleagues' data explicitly allowed by the spec (e.g., name and
  role on a published schedule) — never contacts or profile details by default.
- Consent MUST be captured explicitly with a timestamp before any profile data is stored as
  an active record; registration without consent MUST be rejected.
- Data collection MUST be limited to fields that serve scheduling or the registration form;
  new personal fields require a stated purpose in the spec.
- A volunteer's data-removal request MUST erase or anonymize personal data while keeping only
  anonymous participation records.
- Minors MUST require coordinator confirmation and a guardian contact before becoming active.

**Rationale**: The app holds personal data of church volunteers, including minors. A breach or
over-sharing would harm real people and the ministry's trust.

### II. Secure by Default

- Authentication is mandatory for everything except the public registration form and its
  confirmation page. Sessions MUST expire, be revocable, and be invalidated on logout.
- Login codes or passwords MUST be rate-limited, single-use where applicable (OTP), and never
  logged. Passwords, if used, MUST be hashed with a modern adaptive algorithm.
- The public registration form MUST be protected against abuse (rate limiting, bot
  protection, duplicate detection by phone number).
- All input MUST be validated server-side; all output MUST be encoded; queries MUST be
  parameterized. CSRF protection MUST be enabled on state-changing requests.
- All traffic MUST use HTTPS with secure, HttpOnly, SameSite cookies and sensible security
  headers (CSP, HSTS, frame protection).
- Secrets MUST live in environment configuration, never in the repository.
- Changes to schedules, approvals, registrations and roles MUST be written to an append-only
  audit log (who, what, when).
- Dependencies MUST come from maintained sources and be kept free of known critical
  vulnerabilities.

**Rationale**: A small volunteer team cannot run incident response; the safe path must be the
default path so security does not depend on everyone remembering it.

### III. Effortless for Coordinators and Volunteers

- The interface MUST be in Portuguese, use plain non-technical language, and show dates and
  times in the Africa/Maputo time zone.
- Design is mobile-first: every volunteer flow MUST work fully on a small phone screen and as
  an installable app (PWA); coordinator flows MUST work on phone and desktop.
- Frequent volunteer actions (view my next shift, decline a shift, register an absence,
  request a swap) MUST be reachable within 3 taps from the personal area home.
- Forms MUST validate inline, explain errors in plain words next to the field, preserve
  entered data on error, and never lose a partially completed registration on a slow network.
- Destructive or broadcast actions (cancel event, publish schedule, reject registration) MUST
  show a clear confirmation stating who will be affected.
- The UI MUST meet WCAG 2.1 AA (contrast, touch target size, labels, keyboard and screen
  reader support).
- Security measures MUST not create avoidable friction: prefer low-effort secure mechanisms
  (e.g., one-time codes, long-lived revocable sessions on trusted devices) over complex ones.

**Rationale**: If volunteers find the app hard, they return to scattered WhatsApp messages and
the system fails, however correct it is. Spec success criteria SC-001 and SC-002 depend on it.

### IV. Schedule Integrity & Transparency

- Hard constraints (availability, absences, minimum level, max frequency, one position per
  event, no overlapping times including setup) MUST never be violated by automatic
  generation; unfilled positions are reported as gaps instead.
- Manual coordinator edits MAY override constraints only after an explicit warning naming the
  violated rule, and the override MUST be audited.
- The system MUST never silently remove or change a published assignment; conflicts are
  flagged and the affected people notified.
- Generation results MUST be deterministic for the same inputs and explainable (why a person
  was chosen or why a gap exists).

**Rationale**: Volunteers trust the schedule only if it is fair and predictable (SC-003,
SC-005).

### V. Respectful, Reliable Notifications

- Notifications MUST be sent for the events defined in the spec and respect each user's
  chosen preferences.
- Push payloads MUST contain only what is needed (event, date, role, arrival time) — no
  sensitive personal data.
- Failed deliveries MUST be retried and expired subscriptions cleaned up; users without
  working notifications MUST see in-app guidance to enable them.

**Rationale**: Notifications replace group chats; they must arrive (SC-007) without becoming
noise or leaking data on lock screens.

### VI. Tested Where It Matters, Simple Everywhere Else

- Automated tests are REQUIRED for: authorization rules (each role × each sensitive
  resource), the schedule generation constraints, swap/absence state transitions, and the
  registration/consent flow.
- Security-relevant and constraint-engine changes MUST include a test that fails without the
  change.
- Start with the simplest architecture that satisfies the spec (one deployable app, one
  database); added complexity MUST be justified in the plan's Complexity Tracking table.

**Rationale**: Bugs in access control or scheduling hurt people directly; elsewhere, simplicity
keeps the app maintainable by a small team.

## Security & Privacy Requirements

- Roles: exactly two in this phase (Coordinator, Volunteer); coordinators have equal
  permissions. New roles require a constitution-checked spec change.
- Personal data MUST be backed up regularly, with backups encrypted and restore tested.
- Production logs MUST not contain passwords, OTP codes, tokens or sensitive profile fields.
- Exports and print views (PDF) of schedules MUST include only schedule data, not profile data.
- Account recovery MUST not reveal whether a phone number or e-mail is registered beyond what
  the duplicate-registration flow requires.

## Development Workflow & Quality Gates

- Every feature follows the Spec Kit flow: spec → plan (with Constitution Check) → tasks →
  implementation.
- The plan's Constitution Check MUST explicitly confirm Principles I–VI; any violation MUST be
  recorded with justification in Complexity Tracking.
- Every change touching authentication, authorization, personal data or the scheduling engine
  MUST be reviewed against this constitution before merge (a security review is recommended).
- A feature is done only when: required tests pass, the UI was checked on a phone-sized
  screen, and Portuguese copy was reviewed for clarity.

## Governance

- This constitution supersedes other project practices. Where a spec or plan conflicts with
  it, the constitution wins until it is amended.
- Amendments require: a written proposal explaining the change, approval by a coordinator /
  project owner, an updated Sync Impact Report, and updates to dependent templates.
- Versioning follows semantic versioning: MAJOR for removing or redefining a principle, MINOR
  for adding a principle or materially expanding guidance, PATCH for clarifications.
- Compliance is reviewed at each plan's Constitution Check and at every review of
  security-sensitive changes.

**Version**: 1.0.1 | **Ratified**: 2026-10-18 | **Last Amended**: 2026-10-18
