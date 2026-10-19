# Contract: Web Routes & Inertia Pages

Server-rendered via Inertia. Paths are Portuguese (user-facing); route names and page
components are English. Every non-public route requires `auth` and is authorised by a
**Policy** (Principle I) — UI hiding is never relied on.

Legend — **Access**: `public`, `pending` (logged-in volunteer awaiting approval), `vol`
(active volunteer), `coord` (coordinator; two-factor optional). Middleware `coord` = `auth`,
`role:coordinator`.

## Shared Inertia props (every page)

```ts
{
  auth: { user: { id, name, role, volunteerStatus: string | null } | null },
  flash: { success?: string, error?: string },
  unreadNotifications: number,
  pushPrompt: boolean          // true when no active push subscription on this device
}
```

Never shared: phone, e-mail of other users, any 🔒 field.

## Public

| Method | Path | Name | Page / Action | Notes |
|--------|------|------|---------------|-------|
| GET | `/cadastro` | `registration.create` | `registration/create` or `registration/closed` | multi-step form (sections I–VII + declaração); closed page when `registration_open` is false or `registration_closes_at` passed |
| POST | `/cadastro` | `registration.store` | → `/cadastro/enviado` | throttle 5/h/IP, honeypot, Turnstile; 422 field errors; duplicate phone → message + link to recover account |
| GET | `/cadastro/enviado` | `registration.submitted` | `registration/submitted` | |
| GET/POST | `/entrar`, `/sair`, `/recuperar-senha`, `/2fa` | Fortify | `auth/*` | login by phone; throttle 5/min |

## Volunteer area (`vol`, `pending` sees only status page)

| Method | Path | Name | Page | Props (minimum) |
|--------|------|------|------|-----------------|
| GET | `/` | `home` | `volunteer/home` | next shifts (3) with one-tap "Não posso ir", open swap requests I can take |
| GET | `/estado` | `volunteer.status` | `volunteer/status` | registration status, review notes (`pending` / `needs_correction`) |
| GET | `/minha-escala` | `my-shifts.index` | `volunteer/shifts/index` | own assignments with event, role, arrival time, status |
| POST | `/minha-escala/{assignment}/recusar` | `my-shifts.decline` | — | Policy: owner, status confirmed, before event start; confirm dialog; opens swap request + notifies coordinators |
| GET | `/escala/{schedule}` | `schedules.show` | `schedules/show` | published only; colleagues as "Nome A." + role (R3) |
| GET/POST/DELETE | `/ausencias` | `absences.*` | `volunteer/absences/*` | own only |
| GET/POST | `/trocas` | `swaps.*` | `volunteer/swaps/*` | create (target or open), list mine and open-to-me |
| POST | `/trocas/{swap}/aceitar` | `swaps.accept` | — | re-checks eligibility server-side |
| POST | `/trocas/{swap}/cancelar` | `swaps.cancel` | — | requester only |
| GET/PUT | `/perfil` | `profile.*` | `volunteer/profile/edit` | own data incl. 🔒 fields; availability, skills interest |
| POST | `/perfil/pausa` / `/perfil/retomar` | `profile.pause` / `profile.resume` | — | optional return date; confirm dialog lists affected shifts |
| GET/PUT | `/perfil/notificacoes` | `notification-preferences.*` | `volunteer/profile/notifications` | |
| GET/DELETE | `/perfil/sessoes` | `sessions.*` | `profile/sessions` | list/revoke devices |
| POST / DELETE | `/perfil/remover-dados` | `data-removal.store` / `.destroy` | — | password confirm; schedules anonymisation in 7 days, future shifts → "Por substituir", coordinators notified; DELETE cancels within the 7 days |

## Coordinator area (`coord`, prefix `/gestao`)

| Method | Path | Name | Page |
|--------|------|------|------|
| GET | `/gestao` | `coord.dashboard` | `coord/dashboard` — pending approvals, urgent swaps, gaps next 30 days |
| GET | `/gestao/cadastros` | `coord.registrations.index` | TanStack Table of pending |
| GET | `/gestao/cadastros/{volunteer}` | `coord.registrations.show` | full profile (🔒 decrypted) |
| POST | `/gestao/cadastros/{volunteer}/{aprovar\|rejeitar\|correcao}` | `coord.registrations.*` | — |
| GET/PUT | `/gestao/voluntarios[/{volunteer}]` | `coord.volunteers.*` | list, edit levels per role, pause/resume; filter "sem turnos há 3 meses" (suggestions) |
| CRUD | `/gestao/funcoes` | `coord.team-roles.*` | catalog |
| CRUD | `/gestao/modelos` | `coord.templates.*` | recurring activity templates + positions |
| CRUD | `/gestao/eventos` | `coord.events.*` | calendar; edit occurrence; cancel (confirm dialog lists affected) |
| GET/POST | `/gestao/escalas` | `coord.schedules.*` | create period, generate |
| GET | `/gestao/escalas/{schedule}` | `coord.schedules.edit` | month grid editor (tablet/desktop), gaps highlighted; on phone: list of events → per-event editor (FR-030) |
| POST | `/gestao/escalas/{schedule}/gerar` | `coord.schedules.generate` | regenerate (keeps locked) |
| PUT | `/gestao/atribuicoes/{assignment}` | `coord.assignments.update` | body includes `lock_version`; stale → error "Alterado por <nome> — recarregar"; hard-constraint violation → warning unless `override_note` sent |
| POST | `/gestao/escalas/{schedule}/publicar` | `coord.schedules.publish` | confirm dialog: N volunteers will be notified |
| GET | `/gestao/escalas/{schedule}/pdf` | `coord.schedules.pdf` | dompdf; schedule data only |
| GET | `/gestao/trocas` | `coord.swaps.index` | urgent first |
| POST | `/gestao/trocas/{swap}/{aprovar\|rejeitar}` | `coord.swaps.decide` | |
| GET | `/gestao/disponibilidade` | `coord.availability` | day × period matrix, filters role/level |
| GET | `/gestao/cobertura` | `coord.coverage` | next 30 days filled/required |
| GET | `/gestao/voluntarios/{volunteer}/historico` | `coord.volunteers.history` | participation, real vs declared frequency |
| GET | `/gestao/auditoria` | `coord.audit` | activity log, read-only; names resolved at read time |
| POST | `/gestao/voluntarios/{volunteer}/link-redefinicao` | `coord.reset-links.store` | issues single-use 24 h reset link, shown once (FR-006) |
| GET/POST | `/gestao/declaracao` | `coord.consent.*` | edit and publish a new declaration version (FR-002) |
| GET/PUT | `/gestao/definicoes` | `coord.settings` | registration open/close + closing date, horizon, reminder hours, urgent threshold, auto-approve |

## Error behaviour

- 403 → friendly Portuguese page ("Não tem acesso a esta página").
- 419 (CSRF/session expired) → reload with toast "A sessão expirou, tente novamente" —
  form data preserved by Inertia.
- 422 → errors shown next to fields.
