# Deploy — Classy Fashion Hub to Render (free tier)

Architecture: Render **Docker** web service (Render has no native PHP
runtime) + external **MySQL** (Render free has no MySQL; Bagisto 2.4
needs MySQL/MariaDB) + sandbox Flutterwave keys.

## 0. What is already done (code side)

- `deploy/Dockerfile` — PHP 8.3 + Apache, pinned `bagisto:2.4.12`,
  our tracked customizations overlaid from this repo, shop assets rebuilt.
- `deploy/entrypoint.sh` — waits for DB, migrates, seeds **once** on a
  fresh database (core data, roles, UGX catalog, brand logo), then serves.
  Redeploys only migrate — **never wipes**.
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
   - Leave the rest as in `render.yaml` (`SEED_DEMO=false`).
4. Deploy. First boot runs migrations + seeding (several minutes) —
   watch Logs for `Classy Fashion Hub catalog seeded`.
5. Set `APP_URL` to your `https://<name>.onrender.com` and redeploy
   (image links depend on it).

## 3. Verify (the Definition of Done, live)

1. `/up` → 200. Homepage shows products with USh prices + brand logo.
2. Log in as `admin@example.com / admin123` → **change the password
   immediately**, create the worker/customer or confirm seeded ones.
3. As customer: browse → variant → COD checkout → order history.
4. As worker: confirm → paid → invoice → ship → delivered via buttons.
5. Reporting → Sales totals + CSV export.
6. Mobile money (needs sandbox keys): charge → approve (test prompt) →
   Paid order with tx ref.

## 4. Free-tier caveats (accepted for the demo)

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
- **Mail:** `MAIL_MAILER=log` until real SMTP is configured (order emails
  go to the log; admin dashboard notices still work).
- **HTTPS** is provided by Render; `SESSION_SECURE_COOKIE=true` is set.
