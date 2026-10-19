# Contract: Notifications (Web Push + in-app)

Every notification is a queued Laravel Notification sent on `database` (in-app centre) and
`webpush` channels, filtered by the user's `notification_preferences` (FR-025).

## Push payload (service worker `push` event)

```json
{
  "title": "Nova escala publicada",
  "body": "Dom 2 Nov · Culto de Domingo · Áudio · chegada 07:30",
  "url": "/minha-escala",
  "tag": "assignment-123",
  "type": "schedule_published"
}
```

`shift_check` also carries
`"actions": [{"action": "decline", "title": "Não posso ir", "url": "/minha-escala?recusar={id}"}]`;
the in-app page then opens the decline confirmation (a push never declines by itself).

Rules (Principle V): ≤ 120 chars body; no phone, e-mail, birth date, motivations or other 🔒
data; `url` is a relative in-app path; `tag` collapses repeated updates for the same item.
TTL 24 h (reminders: until event start). Urgency `high` for urgent swaps and cancellations.

`notificationclick` → focus an open client or open `url` (or the tapped action's `url`);
only same-origin paths are ever opened.

## Types

| Type | Recipient | Trigger | FR / US |
|------|-----------|---------|---------|
| `registration_submitted` | coordinators | new registration | US1, FR-023 |
| `registration_decided` | volunteer | approved / rejected / correction requested | US1 |
| `schedule_published` | each assigned volunteer | publish | US3, US6 |
| `assignment_changed` | affected volunteer(s) | edit after publish | FR-023 |
| `event_cancelled` | assigned volunteers | event cancel | US2 |
| `shift_check` | assigned volunteer | `check_reminder_hours_before` (72 h); action "Não posso ir" → decline flow | US4 |
| `shift_reminder` | assigned volunteer | `reminder_hours_before` (24 h) | US6 |
| `swap_requested` | target or eligible volunteers (open) | swap created | US4 |
| `swap_accepted` | coordinators, requester | colleague accepts | US4 |
| `swap_decided` | requester + acceptor | approved / rejected | US4 |
| `replacement_needed` | coordinators | absence/decline creates gap | US4 |
| `availability_conflict` | coordinators | availability change hits published shift | edge case |
| `data_removal_requested` | coordinators | volunteer asks for data removal; upcoming shifts flagged | FR-028 |

## Delivery

- Dispatched after DB commit (`afterCommit`) on the queue; target SC-007 < 1 min.
- `shift_check` / `shift_reminder`: `shifts:remind` runs every 5 minutes and marks
  `assignments.check_sent_at` / `reminder_sent_at`, so each goes out once per assignment
  (reset when another volunteer takes the place). A shift booked inside the reminder
  window gets only the reminder.
- 404/410 from push service → delete subscription.
- Other failures → retry 3× with backoff; in-app copy always persists.
- Users with no subscription see the `pushPrompt` banner with install instructions (iOS:
  "Adicionar ao ecrã principal" first).
