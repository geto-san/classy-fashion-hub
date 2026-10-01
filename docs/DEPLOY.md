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

Render free offers PostgreSQL only — Bagisto 2.4 cannot use it. Use a
free MySQL **without mandatory TLS** (keeps this deploy simple):

1. Sign up at Clever Cloud (or Railway trial) and create a **MySQL** add-on.
2. Note: host, port, database, username, password.
3. Alternatives needing TLS setup (advanced): TiDB Serverless, Aiven.

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
- **Ephemeral disk:** uploads (product images, re-uploaded logos) vanish
  on redeploy. Seeded catalog/brand restore automatically on a **fresh**
  database only. After a redeploy, check the logo and re-upload in
  Configuration → General → Design if missing.
- **No queue worker / scheduler:** `QUEUE_CONNECTION=sync` (mail +
  indexing run inline); date-bound prices need the cron entry from the
  Bagisto deployment docs (VPS step-up).
- **Mail:** `MAIL_MAILER=log` until real SMTP is configured (order emails
  go to the log; admin dashboard notices still work).
- **HTTPS** is provided by Render; `SESSION_SECURE_COOKIE=true` is set.
