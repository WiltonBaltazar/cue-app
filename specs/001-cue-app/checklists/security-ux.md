# Security, Privacy & Usability Requirements Checklist: Cue App

**Purpose**: Quick author self-check of how well the spec's security, privacy and usability
requirements are written (completeness, clarity, consistency, measurability), before
`/speckit-tasks`. Tests the requirements, not the implementation.
**Created**: 2026-10-18
**Feature**: [spec.md](../spec.md) · Constitution Principles I–III

## Access & Authentication

- [x] CHK001 - Is there an explicit role × data visibility rule for every entity, beyond the sensitive fields listed (e.g., who sees participation history, swap requests, absence dates)? [Completeness, Spec §FR-026, §FR-022, Gap]
- [x] CHK002 - Is the visibility of absence reasons defined (coordinators only, or also colleagues affected by a swap)? [Ambiguity, Spec §FR-016]
- [x] CHK003 - Are account security rules (failed-login limits, session duration, signing out other devices) stated as requirements in the spec rather than only in the plan? [Gap, Spec §FR-006]
- [x] CHK004 - Is the coordinator-generated password reset link specified with expiry, single use and a safe way to deliver it? [Clarity, Spec §FR-006]
- [x] CHK005 - Are abuse protections for the public registration link (rate limits, bot protection) captured as requirements? [Gap, Spec §FR-001]

## Personal Data & Privacy

- [x] CHK006 - Does the spec define what happens when the consent declaration text changes (versioning, asking existing volunteers to accept again)? [Gap, Spec §FR-002]
- [x] CHK007 - Is it clear whether a minor's guardian must give consent, or only provide a contact? [Ambiguity, Spec §FR-002a]
- [x] CHK008 - Are retention periods defined for inactive volunteers and for audit records, consistent with the 30-day rule for rejected registrations? [Consistency, Spec §FR-003a, §FR-029, Gap]
- [x] CHK009 - Is "registos anónimos de participação" defined (which fields remain), and is it consistent with an audit log that records names? [Conflict, Spec §FR-028, §FR-029]
- [x] CHK010 - Is the scope of the audit log complete (role changes, constraint overrides, data-removal requests, views of sensitive profiles)? [Completeness, Spec §FR-029]
- [x] CHK011 - Are the personal-data limits for PDF/print exports and push messages specified, consistent with the short-name rule? [Consistency, Spec §FR-015, §FR-023, §FR-027]

## Usability & Mobile Experience

- [x] CHK012 - Is the "menos de 10 minutos" target backed by requirements for form structure (steps, progress, saving partial progress on a weak connection)? [Measurability, Spec §SC-001, Gap]
- [x] CHK013 - Are mobile-first and quick-action targets (e.g., at most 3 taps for frequent volunteer actions) written as measurable success criteria in the spec? [Measurability, Gap]
- [x] CHK014 - Is the accessibility level (WCAG 2.1 AA) stated in the spec, not only in the constitution? [Gap]
- [x] CHK015 - Are empty and status states defined for the volunteer area (no shifts yet, registration pending or needs correction, registration closed, paused)? [Coverage, Spec §US1, §FR-001, §FR-005a, Gap]
- [x] CHK016 - Are confirmation requirements listed consistently for every destructive or broadcast action (publish, cancel event, decline shift, pause, data removal)? [Consistency, Spec §US2-4, §US4-5, §FR-005a, §FR-028]
- [x] CHK017 - Is there a defined experience for users who never enable push (in-app notification centre, reminders still visible)? [Coverage, Spec §US6-4, §FR-023]
- [x] CHK018 - Are coordinator flows required to work on a phone, including the schedule editor, or is desktop assumed? [Ambiguity, Gap]
- [x] CHK019 - Is terminology used consistently ("turno" vs "atribuição", "função" vs "competência", "Por substituir")? [Consistency]

## Notes

- Check items off as completed: `[x]`. When an item exposes a gap, fix it in `spec.md` (or run
  `/speckit-clarify`) rather than in the plan only.
- Items marked [Gap] are missing from the spec; [Ambiguity]/[Conflict] need a decision.
