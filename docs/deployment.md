# Deployment

The recommended way is **Docker on Coolify** (below): one image runs Nginx, PHP-FPM, the
queue worker and the scheduler under Supervisor, next to PostgreSQL 17 and Redis. Nothing has
to be installed or scheduled by hand. Sections 1–8 describe the same setup on a plain
VPS without Docker.

## Coolify (Docker)

Files: `Dockerfile`, `docker-compose.yml`, `docker/` (Nginx, PHP, PHP-FPM, Supervisor,
entrypoint).

1. **New resource → Docker Compose**, from this repository, branch `main`, compose file
   `docker-compose.yml`.
2. **Domain**: on the `app` service, e.g. `https://escalas.example.org` (port 80). Coolify
   issues the certificate.
3. **Environment variables** (Coolify generates `SERVICE_USER_*`/`SERVICE_PASSWORD_*`
   itself). Set at least:

    | Variable                                                                        | How to get it                                                                                                                                          |
    | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
    | `APP_KEY`                                                                       | `php artisan key:generate --show` on any machine. **Never change it later**: the 🔒 fields are encrypted with it. Keep a copy in the password manager. |
    | `APP_URL`                                                                       | The domain above, with `https://`                                                                                                                      |
    | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`                                         | `php artisan webpush:vapid --show`, once. Changing them drops every device's push subscription.                                                        |
    | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`                                    | Cloudflare Turnstile widget for the domain; without them registration is refused                                                                       |
    | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | SMTP account (password resets, backup alerts)                                                                                                          |
    | `BACKUP_*` (optional)                                                           | Off-site bucket, see [Backups](#6-backups). Until then backups stay in the `app-storage` volume (`BACKUP_DISKS=local`).                                |

4. **Deploy.** On every start the container waits for PostgreSQL, runs the migrations and the
   reference seeders (team roles, declaration), links storage and caches the config, then
   Supervisor starts Nginx, PHP-FPM, the queue worker and the scheduler. Set
   `RUN_MIGRATIONS=false` to skip the migrations on a start.
5. **First coordinator**, once: in Coolify, open the `app` container's **Terminal** and run
   `php artisan coordinators:create`.

Data lives in three volumes: `postgres-data`, `redis-data` (queue and sessions) and
`app-storage` (local backups, logs). Logs of every process are in Coolify's log view.
To update, push to `main` and redeploy (or turn on auto-deploy); remember to commit the
frontend build (`npm run build`) with frontend changes.

**Running the stack locally** (`docker compose up`): Coolify's generated variables don't
exist outside Coolify, so add them to `.env` first, or PostgreSQL refuses to start
("superuser password is not specified"):
`SERVICE_USER_POSTGRES`, `SERVICE_PASSWORD_POSTGRES` and `SERVICE_PASSWORD_REDIS`.

## 1. Server packages

```bash
sudo apt install nginx mysql-server supervisor certbot python3-certbot-nginx unzip \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-intl php8.4-gd php8.4-bcmath php8.4-gmp
```

- `gmp` and `bcmath` make signing Web Push messages much faster (optional but recommended).
- `zip` is needed by the nightly backup.
- Node is not needed on the server: the built frontend (`public/build`, `public/sw.js`)
  is committed to the repository. Rebuild it with `npm run build` and commit it whenever
  the frontend changes.
- In `php.ini` for FPM, keep `zend.exception_ignore_args = On` (the production default) so
  stack traces never carry argument values such as passwords.

## 2. Application

```bash
cd /var/www/cue-app
git clone <repo> . && git checkout main
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # outside "local" only team roles and the declaration
php artisan coordinators:create      # first coordinator: phone, name, password
php artisan storage:link
php artisan optimize
```

The web user (`www-data`) needs write access to `storage/` and `bootstrap/cache/` only.

> **Never change `APP_KEY` on a running install.** Every 🔒 field (birth date, contacts,
> motivations, absence reasons…) is encrypted with it; a new key makes them unreadable,
> backups included. Keep a copy of the key in the team's password manager.

### Environment

| Variable                                                   | Value                                                                                                       |
| ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `APP_ENV` / `APP_DEBUG`                                    | `production` / `false`                                                                                      |
| `APP_URL`                                                  | `https://<domain>` (HTTPS; secure cookies and push depend on it)                                            |
| `APP_TIMEZONE` / `APP_DISPLAY_TIMEZONE`                    | `UTC` / `Africa/Maputo`                                                                                     |
| `DB_*`                                                     | MySQL 8.4 database and a user with rights on that database only                                             |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`        | `database`                                                                                                  |
| `SESSION_ENCRYPT`                                          | `true`                                                                                                      |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL`                  | `stack` / `daily` / `warning`                                                                               |
| `MAIL_*`                                                   | SMTP account used for password resets and backup alerts                                                     |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`              | Cloudflare Turnstile widget for the domain. Without them registration is refused in production.             |
| `VAPID_SUBJECT` / `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | Generate once with `php artisan webpush:vapid`. Changing them later drops every device's push subscription. |
| `BACKUP_*`                                                 | See [Backups](#6-backups)                                                                                   |

After changing `.env`: `php artisan optimize` and `sudo supervisorctl restart cue-worker:*`.

## 3. Nginx and TLS

```nginx
server {
    listen 80;
    server_name <domain>;
    root /var/www/cue-app/public;
    index index.php;

    client_max_body_size 5m;

    # Compressed responses roughly halve load time on a 3G phone (Lighthouse T133).
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/css application/javascript application/json application/manifest+json image/svg+xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # The service worker and manifest must never be cached, or updates stall on phones.
    location = /sw.js {
        add_header Cache-Control "no-cache";
        try_files $uri =404;
    }
    location = /build/manifest.webmanifest {
        add_header Cache-Control "no-cache";
        default_type application/manifest+json;
        try_files $uri =404;
    }

    # Other built assets carry a content hash in their name.
    location /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo certbot --nginx -d <domain>   # adds the 443 server and the HTTP → HTTPS redirect
```

The app sets HSTS, CSP and the other security headers itself (`SecurityHeaders`
middleware), so Nginx should not add them again.

## 4. Queue worker (Supervisor)

Notifications (push, e-mail) and schedule changes are queued. `/etc/supervisor/conf.d/cue-worker.conf`:

```ini
[program:cue-worker]
command=php /var/www/cue-app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
process_name=%(program_name)s_%(process_num)02d
numprocs=1
user=www-data
autostart=true
autorestart=true
stopasflag=TERM
stopwaitsecs=60
redirect_stderr=true
stdout_logfile=/var/www/cue-app/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Push must reach phones within a minute (SC-007), so keep the worker running at all times.

### Shared hosting (no Supervisor)

Set `QUEUE_WORK_FROM_SCHEDULER=true` in `.env` and skip Supervisor. The scheduler then
starts a worker every minute that runs the queue for 50 seconds and exits, so the single
cron line below also delivers push and e-mail, within about a minute. Leave it `false`
where a Supervisor worker runs, so two workers don't compete.

## 5. Scheduler (cron)

```cron
* * * * * www-data cd /var/www/cue-app && php artisan schedule:run >> /dev/null 2>&1
```

`php artisan schedule:list` shows the jobs: event occurrences, swap expiry, 72 h and 24 h
reminders, rejected-registration purge, data-removal processing, notification pruning and
the backups.

## 6. Backups

`spatie/laravel-backup` runs every night (`routes/console.php`):

| Time (server) | Job                                                                                                                 |
| ------------- | ------------------------------------------------------------------------------------------------------------------- |
| 01:00         | `backup:clean` — deletes backups older than 30 days                                                                 |
| 01:30         | `backup:run` — dumps the database (gzip) and `storage/app` into a zip encrypted with AES-256, checks it, uploads it |
| 07:00         | `backup:monitor` — e-mails `BACKUP_MAIL_TO` if the newest backup is missing or older than a day                     |

Failures are e-mailed to `BACKUP_MAIL_TO`; successful runs are not.

The archive goes to the `backups` disk, an S3-compatible bucket at another provider
(Cloudflare R2, Backblaze B2, Wasabi or AWS). Use a bucket and access key that are used only
for backups, with no public access:

```dotenv
BACKUP_DISKS=backups
BACKUP_ARCHIVE_PASSWORD=<long random password, kept in the password manager>
BACKUP_MAIL_TO=<coordinator or admin e-mail>
BACKUP_AWS_ACCESS_KEY_ID=
BACKUP_AWS_SECRET_ACCESS_KEY=
BACKUP_AWS_DEFAULT_REGION=auto
BACKUP_AWS_BUCKET=
BACKUP_AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com
BACKUP_AWS_USE_PATH_STYLE_ENDPOINT=false
```

To restore you need three secrets, so keep all three in the password manager:
`BACKUP_ARCHIVE_PASSWORD` to open the zip, `APP_KEY` to read the 🔒 fields, and the bucket key.

Check the setup with `php artisan backup:run` and then `php artisan backup:list`.

### Restore

1. Download the zip you need from the bucket, or run `php artisan backup:list` to see them.
2. Extract it with the archive password. `7z x <file>.zip` works; macOS's built-in `unzip`
   cannot open AES-encrypted zips.
3. Load the dump into an empty database:
   `gunzip -c db-dumps/pgsql-<db>.sql.gz | psql -U <user> -d <empty_db>` (PostgreSQL, the
   Docker setup) or `gunzip -c db-dumps/mysql-<db>.sql.gz | mysql -u <user> -p <empty_db>`
4. Point an app with the **same `APP_KEY`** at that database, then check that it opens and
   that a volunteer's profile shows their answers.
5. Copy back `storage/app` from the archive if files are needed.

### Restore drills

Do one each month, on staging, never on production (research R21). Log each drill here.

| Date       | Where                                   | Backup                                                     | Result                                                                                                                                                                                                                                                   | By                 |
| ---------- | --------------------------------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| 2026-10-19 | Local development (SQLite), not staging | `backup:run --only-db`, to the local disk, AES-256 archive | Archive is encrypted and opens with the password. The restored database passed `pragma integrity_check` and its row counts match the live database (70 users, 69 volunteers, 26 events, 42 assignments, 1 schedule). 🔒 fields decrypt with the app key. | Claude Code (T130) |
| _pending_  | Staging (MySQL 8.4)                     | First nightly backup in the off-site bucket                | —                                                                                                                                                                                                                                                        | —                  |

## 7. Updating

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
sudo supervisorctl restart cue-worker:*
php artisan up
```

Phones pick up the new service worker the next time they open the app.

## 8. After deploying

- [ ] `https://<domain>/cadastro` opens; the Turnstile widget appears
- [ ] A coordinator can sign in; `/gestao` loads
- [ ] On an Android phone, "Instalar a app" (or Chrome menu → Instalar app) works, and
      "Ativar notificações" brings a test push within a minute
- [ ] On an iPhone, after "Adicionar ao ecrã principal", push can be turned on from the
      installed app
- [ ] `php artisan schedule:list` lists every job, and the cron line is installed
- [ ] `sudo supervisorctl status` shows `cue-worker` RUNNING
- [ ] `php artisan backup:run` succeeds and the zip shows up in the bucket
