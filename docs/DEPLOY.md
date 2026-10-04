# Deploy — Classy Fashion Hub to Render (free tier)

Architecture: Render **Docker** web service (Render has no native PHP
runtime) + external **MySQL** (Render free has no MySQL; Bagisto 2.4
needs MySQL/MariaDB) + sandbox Flutterwave keys.

## 0. What is already done (code side)

- `deploy/Dockerfile` — PHP 8.3 + Apache, pinned `bagisto:2.4.12`,
  our tracked customizations overlaid from this repo (the exact commit Render
  is deploying when it passes `RENDER_GIT_COMMIT`, otherwise `main`), shop
  assets rebuilt.
- `deploy/entrypoint.sh` — waits for DB, migrates, seeds **once** on a
  fresh database (core data, roles, UGX catalog, brand logo), runs
  `classy:secure-accounts`, then serves. Redeploys only migrate — **never
  wipes**.
- `deploy/render.yaml` — Render Blueprint with all env vars.
- Health check: `/up`.

## 1. Create the database (~10 min, your clicks)

Render free offers PostgreSQL only — Bagisto 2.4 cannot use it. Use the
**Railway MySQL** plugin (trial credit covers a demo; watch usage):

| Railway variable | Render env var |
|---|---|
| `MYSQLHOST` | `DB_HOST` |
| `MYSQLPORT` | `DB_PORT` |
| `MYSQLDATABASE` | `DB_DATABASE` |
| `MYSQLUSER` | `DB_USERNAME` |
| `MYSQLPASSWORD` | `DB_PASSWORD` |

- Ignore `MYSQL_URL`, `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE` (alternate
  naming) and all `RAILWAY_*` system vars — Render does not need them.
- Use the **public** proxy host Railway shows (Render connects over the
  internet, not Railway's private network). No TLS setup needed.
- Alternatives: Clever Cloud free MySQL; TiDB Serverless / Aiven (both
  enforce TLS — advanced).

## 2. Deploy on Render (~10 min + ~15 min first build)

1. Push this repo (done: `main`).
2. Render Dashboard → New → **Web Service** → connect
   `geto-san/classy-fashion-hub` → runtime **Docker**.
   (Or New → Blueprint → select the repo; `deploy/render.yaml` is used.)
3. Set environment variables (Render → Environment):
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
     from step 1.
   - `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_SECRET_KEY`,
     `FLUTTERWAVE_SECRET_HASH` (sandbox keys; empty = method hidden).
   - `SEED_ADMIN_PASSWORD` (and optionally `SEED_ADMIN_EMAIL`): the admin
     login you want. Blank = a random password is printed **once** in the
     deploy log (search the log for `NEW admin password`).
   - `SEED_WORKER_PASSWORD` / `SEED_CUSTOMER_PASSWORD` only matter with
     `SEED_DEMO=true`, which creates the demo worker/customer accounts
     (`render.yaml` currently ships `SEED_DEMO=true` for client testing, so set
     these to the logins you will give testers; otherwise they are random).
   - Mail (optional but needed for password reset and order e-mails):
     `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
     `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `ADMIN_MAIL_ADDRESS` for
     low-stock alerts. Leave `MAIL_MAILER=log` until you have a provider.
   - Keep `FLUTTERWAVE_SANDBOX=true` with test keys; use `false` only with
     live keys. A mismatch hides Mobile Money.
   - Leave the rest as in `render.yaml`.
   - **Set `SEED_ADMIN_PASSWORD` before the first deploy of this version.**
     On that boot the old `admin123` password is replaced; without the
     variable the new one is random and appears once in the log.
   - **Locked out?** Set `SEED_ADMIN_PASSWORD` to a new password and
     `RESET_ADMIN_PASSWORD=true`, redeploy, sign in, then delete
     `RESET_ADMIN_PASSWORD` (it would otherwise reset the password on every
     boot).
4. Deploy. First boot runs migrations + seeding (several minutes) —
   watch Logs for `Classy Fashion Hub catalog seeded`.
5. Set `APP_URL` to your `https://<name>.onrender.com` and redeploy
   (image links depend on it).

## 3. Verify (the Definition of Done, live)

1. `/up` → 200. Homepage shows products with UGX prices + brand logo.
2. Log in as `admin@example.com` (or `SEED_ADMIN_EMAIL`) with the password
   from step 2 (never `admin123` on production) → change it, then create real
   worker accounts under Settings → Users.
3. As customer: browse → variant → COD checkout → order history.
4. As worker: confirm → paid → invoice → ship → delivered via buttons.
5. Reporting → Sales totals + CSV export.
6. Mobile money (needs sandbox keys): charge → approve (test prompt) →
   Paid order with tx ref.

## 4. Backups (report 10.5)

- `scripts/backup.sh` — timestamped `db.sql` (single-transaction dump)
  + `storage-public.tar.gz`; keeps the last 7 in `backups/` (gitignored).
  Credentials via `DB_*` env, same names as the app.
- `scripts/restore.sh backups/<stamp>` — restores into an **empty**
  database (refuses otherwise) and unpacks media.
- These are **manual** scripts: nothing schedules them and **no restore drill
  has been recorded**. Run `backup.sh` before any risky change and nightly
  during the demo period (e.g. a cron line `0 2 * * * cd /path/to/repo &&
  ./scripts/backup.sh` on any machine that can reach the database), and copy
  `backups/` off-machine.
- Restore drill (do once before grading, then fill in the line below):
  create an empty database, run `scripts/restore.sh backups/<stamp>`, open the
  shop and check the product count and the latest order.
  `Last successful restore drill: ____ (date, who, backup stamp)`

## 5. Free-tier caveats (accepted for the demo)

- **Sleep:** the service sleeps when idle; first load takes ~1 min.
- **Media without AWS/Cloudinary:** Render disk is ephemeral, so
  `classy:repair-images` (runs on every boot, and manually any time)
  regenerates any missing product images as branded placeholders and
  restores the brand logo/favicon from committed copies. Seeded/brand
  media therefore survives redeploys. Admin-uploaded images are still
  ephemeral — re-upload after a redeploy if needed.
  (Cloudinary was evaluated and rejected: the only Laravel 12-compatible
  Flysystem adapter has broken reads and 404ing URLs upstream; see
  KNOWN_LIMITATIONS.)
- **No queue worker / scheduler:** `QUEUE_CONNECTION=sync` (mail +
  indexing run inline); date-bound prices need the cron entry from the
  Bagisto deployment docs (VPS step-up).
- **Mail:** `MAIL_MAILER=log` until real SMTP is configured (customer
  status e-mails, password reset and low-stock alerts go to the log instead
  of being sent).
- **HTTPS** is provided by Render; `SESSION_SECURE_COOKIE=true` is set.
