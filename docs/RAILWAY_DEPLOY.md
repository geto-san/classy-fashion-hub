# Railway deployment

Deploy one PHP/Apache service (storefront and admin together), one MySQL service,
and one persistent upload volume. This guide prepares a deployment; no Railway
resources have been created by the agent.

## What you need

- Railway account with sufficient credits or a paid plan, and access to this GitHub repository.
- A MySQL 8.x database service in the same Railway project/environment.
- A stable Laravel APP_KEY and an initial administrator password of at least 12 characters.
- A Resend account, API key and verified sending domain (DNS access required).
- A persistent Railway volume. B2 remains a later storage migration, not an environment-only switch.
- Optional: real product photographs, supplier costs and MTN merchant/sandbox keys.

Keep credentials in Railway service Variables. Do not put them in Git or chat.

## 1. Commit the prepared files

Review and commit the working tree, including the new ClassyFashion files and
core overrides. No files have been committed or pushed automatically. Railway
builds the selected Git revision, not your uncommitted local changes.

The Docker context uses an explicit `.dockerignore` allowlist. Add new application
files to that allowlist when adding files later. Local `.env`, vendor, node_modules,
logs and caches are excluded. Do not place secrets in deployment templates.

## 2. Create services

1. Create a Railway project and add a MySQL database. Name the service `MySQL`.
2. Add the application from this GitHub repository. Keep its root directory `/`.
3. Set `RAILWAY_DOCKERFILE_PATH=deploy/railway.Dockerfile` in the app variables.
   Use the Dockerfile builder. Keep build/start command overrides empty: the
   image entrypoint starts Apache and performs migrations.
4. Set the health check path to `/up`, with a 600-second startup timeout.
   Use one replica initially: first boot seeds data and performs migrations.
5. Attach a volume to the **application** at `/var/www/html/storage`.
   The MySQL service also needs its own persistent database volume.
6. Set `PORT=8080`; generate a public domain with target port **8080**.
   Set APP_URL to that complete HTTPS URL before accepting traffic.
7. Disable application sleeping/serverless mode for business use. Configure
   billing alerts and monitor usage; these services consume Railway resources.

The Railway build installs pinned Bagisto 2.4.12, overlays this checkout, installs
locked dependencies and rebuilds the shop assets. It uses PHP 8.4. It does not
clone a different version of the repository during the build.

## 3. Application variables

Use `deploy/production.env` as a placeholder checklist. Replace every required
empty value in Railway; do not upload placeholder values as live configuration.

```dotenv
APP_NAME="Classy Fashion Hub"
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
LOG_LEVEL=warning
APP_DEBUG_ALLOWED_IPS=
APP_URL=https://YOUR-APP.up.railway.app
APP_ADMIN_URL=admin
APP_TIMEZONE=Africa/Kampala
APP_KEY=base64:YOUR_GENERATED_KEY
PORT=8080

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public

SEED_DEMO=false
SEED_ADMIN_EMAIL=YOUR_ADMIN_EMAIL
SEED_ADMIN_PASSWORD=YOUR_STRONG_PASSWORD

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=resend
MAIL_PASSWORD=YOUR_RESEND_API_KEY
MAIL_FROM_ADDRESS=shop@YOUR_VERIFIED_DOMAIN
MAIL_FROM_NAME="Classy Fashion Hub"
ADMIN_MAIL_ADDRESS=YOUR_ADMIN_RECIPIENT
CONTACT_MAIL_ADDRESS=YOUR_SUPPORT_ADDRESS

MOMO_PROVIDER=auto
```

Variable references must match the actual database service name. Use Railway's
variable-reference selector if it differs from `MySQL`. Do not use a public TCP
proxy for communication between these two services.

Generate APP_KEY locally with `php artisan key:generate --show` inside a supported
PHP environment, then save it in Railway Variables. Keep it unchanged across
restarts and redeployments. The entrypoint refuses a missing/invalid key.
Do not use the disposable Docker test key in production.

Resend requires a verified sending domain. The API key is the SMTP password.
Confirm the Railway plan permits outbound SMTP. If SMTP is unavailable, use Brevo
SMTP on an allowed port or implement Resend's HTTPS transport; do not disable TLS.

For payment testing add MTN_SUBSCRIPTION_KEY, MTN_API_USER_ID, MTN_API_KEY,
MTN_BASE_URL, MTN_TARGET_ENV and MTN_CURRENCY with mutually consistent sandbox
settings. Keep sandbox clearly identified. Alternatively configure MOMO_TILL_NUMBER
for staff-verified payments. Cash on delivery can be tested without these keys.

## 4. First deployment and verification

- Deploy and inspect startup logs for successful migrations, catalogue seeding
  and secure-account initialization. Startup failure should keep traffic off. An existing store with no administrator
  is refused instead of being reseeded; investigate recovery before retrying.
- `/up`, homepage and admin login must return 200. `/up` alone is insufficient.
- Sign in using SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD, never admin123.
- Create real workers; confirm stock editing works and product cost/edit access is refused.
- Register a customer and test cart, size/colour selection, billing-as-shipping
  delivery notes, COD checkout, order history and status updates.
- Test password-reset email and order-status email with a recipient you control.
- Upload a real photo, redeploy, and confirm the photo remains available.
- Verify completed-sales figures and CSV export, including a canceled/unpaid order.
- Only enable live mobile money after provider verification and a supervised transaction.
- Replace placeholder product photos/costs and approve delivery/returns policies.

## 5. Operations

Keep the app at one replica while migrations/first-run seeding occur at startup.
Before schema-changing deployments take a database backup. Image rollback alone
does not roll back database migrations. Test restores into a separate database.

Enable/schedule database and volume backups appropriate to the Railway plan and
keep an off-site copy. Backups and recovery have not been configured remotely.
Local scripts are `scripts/backup.sh` and `scripts/restore.sh`.

Payment expiry is scheduled in Laravel but needs a running scheduler. After first
bootstrap, add a separate service from the same image/variables with command
`php artisan schedule:work` (no public domain). It must connect to the same DB.
The queue remains synchronous; do not switch to `database`/`redis` until a queue
worker is deployed. Multiple service boots should be serialized for migrations.

B2/Cloudinary are not enabled by this guide. The volume protects uploads across
redeploys; off-site backups remain necessary. A later B2 migration must verify
Bagisto image caching, URLs, adapter dependencies and existing-media migration.

## Local verification

```sh
docker build -f deploy/railway.Dockerfile -t classy:railway .
```

The application changes previously passed 79 feature tests (333 assertions) in
an isolated PHP 8.4/MySQL environment. The Railway production image also builds successfully locally, including PHP
extensions, locked dependency installation and the Vite storefront build. Startup-key
checks pass, and an exported build-context audit found no local secrets/dependencies.
A fresh production-mode container also booted against a separate empty MySQL
database and named upload volume on port 8080: homepage, admin login and /up all
returned HTTP 200. Bootstrap created one administrator, zero demo worker/customer
accounts and zero orders. Live SMTP/payment/B2 integrations, real upload persistence
across Railway deployments and remote Railway deployment remain unverified.
Complete the deployment checks above before calling the deployment ready.

## References

- https://docs.railway.com/builds/dockerfiles
- https://docs.railway.com/databases/mysql
- https://docs.railway.com/variables
- https://resend.com/docs/send-with-smtp
