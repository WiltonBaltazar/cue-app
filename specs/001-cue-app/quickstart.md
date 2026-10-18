# Quickstart & Validation: Cue App (001-cue-app)

How to run the app locally and prove each user story works end-to-end. Details live in
[data-model.md](data-model.md) and [contracts/](contracts/); this file is a run guide only.

## Prerequisites

- PHP 8.3+ (8.4 recommended; extensions: pdo_mysql, mbstring, intl, gmp, openssl), Composer 2
- Node.js 22 LTS, npm
- MySQL 8.4
- HTTPS locally for push/service worker testing (Laravel Herd/Valet `secure`, or `localhost`,
  which browsers treat as secure)

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan webpush:vapid          # VAPID keys for push
# set DB_*, APP_TIMEZONE=UTC, APP_DISPLAY_TIMEZONE=Africa/Maputo, TURNSTILE_* in .env
php artisan migrate --seed         # seeds team roles, 1 coordinator, demo volunteers/templates
composer run dev                   # serve + queue:listen + vite + scheduler
```

Seeded logins (local only): coordinator `+258840000001` / `password` (prompts 2FA setup).

## Automated checks

```bash
php artisan test                   # Pest unit + feature
php artisan test --group=browser   # Pest browser smoke flows (mobile viewport)
npm run test                       # Vitest components + a11y
npm run types && npm run lint
vendor/bin/pint --test && vendor/bin/phpstan analyse
composer audit && npm audit --omit=dev
```

All must pass before a feature is "done" (constitution: Quality Gates).

## Validation scenarios

| # | Story | Steps | Expected |
|---|-------|-------|----------|
| 1 | US1 | Open `/cadastro` on a phone viewport, fill all steps, leave declaration unchecked, submit | Blocked with message next to the checkbox; data kept |
| 2 | US1 | Accept declaration, submit | `/cadastro/enviado`; coordinator gets `registration_submitted`; status `pending` |
| 3 | US1 | Submit again with same phone | Duplicate message + "recuperar conta" link |
| 4 | US1 | Coordinator approves in `/gestao/cadastros` | Volunteer logs in and sees home; eligible for scheduling |
| 5 | US2 | Create template "Culto de Domingo" with positions; activate | Occurrences for next 3 months in calendar |
| 6 | US2 | Edit one occurrence's positions | Only that date changes; marked customised |
| 7 | US3 | With seed (≥10 volunteers, 1 month of events) generate November | Draft with no hard-constraint violations; gaps highlighted with suggestions |
| 8 | US3 | Manually assign an unavailable volunteer | Warning naming the rule; saving requires override note; audit entry created |
| 9 | US3 | Publish | Confirmation shows count; assigned volunteers receive push within 1 min |
| 10 | US4 | Volunteer registers absence overlapping a shift | Shift → "Por substituir"; coordinator notified with substitutes |
| 11 | US4 | Volunteer opens a swap; eligible colleague accepts; coordinator approves | Schedule updated; both notified; ineligible users never see the request |
| 12 | US4 | After publish, check a shift's status, then tap "Não posso ir" | Starts Confirmed; decline opens swap request and notifies coordinator |
| 13 | US5 | Open `/gestao/disponibilidade` and `/gestao/cobertura` | Matrix with filters; roles in shortage flagged |
| 14 | US6 | Enable notifications, run `php artisan schedule:test` for reminders | Reminder push received |
| 15 | Sec | Log in as volunteer, request `/gestao/cadastros/1` and `/app-api/assignments/1/substitutes` | 403 for both |
| 16 | Sec | Inspect Inertia props on `/escala/{id}` | Colleagues show only short name + role; no phone/e-mail |
| 17 | Sec | 6 wrong logins in 1 min | Throttled with Portuguese message |
| 18 | Priv | Volunteer requests data removal; travel time +7 days (`php artisan schedule:run` with fake clock) | Future shifts "Por substituir" immediately; after 7 days profile anonymised, history kept as "Voluntário #id"; cancelling within 7 days keeps the profile intact |
| 19 | Priv | Reject a registration; reverse within 30 days; reject another and advance 30 days; re-register same phone | Reversal restores pending; purge deletes data; new registration accepted and flagged "cadastro anterior rejeitado" |
| 20 | Sec | Two coordinators edit the same assignment | Second save refused with "Alterado por <nome> — recarregar" |

## Lighthouse / a11y spot-check

Run Lighthouse (mobile) on `/`, `/cadastro`, `/minha-escala`: PWA installable, Accessibility
≥ 95, no contrast failures.
