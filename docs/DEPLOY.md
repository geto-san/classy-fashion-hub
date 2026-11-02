# Render, Neon, and Cloudinary migration

The application targets Bagisto 2.5.0, Laravel 13, PHP 8.4, and Tailwind 4.
`deploy/Dockerfile` builds the storefront, admin, and backend together from the
pinned upstream skeleton and this checkout. `render.yaml` uses the repository
root as its Docker context and disables automatic deployment during validation.
The alternative Railway Dockerfile uses the same application version.

Render's custom start command explicitly invokes `classy-entrypoint.sh` followed
by `classy-serve.sh`; a custom command bypasses the image entrypoint. Both steps
must run so database validation and the installed marker precede Apache.

Production has not been switched. Provider credentials are stored locally and remote
staging is being validated. The Render service stays paused during database transfer.
Do not remove the running Railway project or its uploads volume.

## Credentials

Use the Git-ignored `.env.deploy.local` file, permission mode 600. Never paste
credentials into chat or commit a populated secret file.

| Variable | Location |
|---|---|
| `RENDER_API_KEY` | Render dashboard → Account Settings → API Keys. Connect GitHub and grant access to this repository. |
| `NEON_API_KEY` | Neon console → profile menu → Settings → API keys. A personal key can create a project. |
| `NEON_PROJECT_ID` | Optional existing project ID; otherwise create a Free project in the region nearest the Render service. |
| `CLOUDINARY_CLOUD_NAME` | Cloudinary dashboard, product environment cloud name. |
| `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET` | Cloudinary console → Settings → API Keys. |
| `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` | Brevo → SMTP & API → API Keys, plus a verified sender. Required before customer launch. |
| `RAILWAY_API_TOKEN` | Railway → Account Settings → Tokens. The locally supplied `Railway_token` alias was also accepted for the export. |

References: [Render API](https://render.com/docs/api),
[Neon keys](https://neon.com/docs/manage/api-keys),
[Cloudinary credentials](https://cloudinary.com/documentation/developer_onboarding_faq_find_credentials).

## Export and rollback

The live `humble-magic` project was exported over SSH into the ignored, private
`backups/railway-live/` directory: SQL, complete `storage/app` archive, and service
variables including the persistent `APP_KEY`. Original dumps and uploaded files
must remain available independently of the new providers. These are rehearsal
snapshots, not a final write-frozen cutover export.

The confirmed migration source is the current Railway database: zero products,
one customer, and one order. Do not merge the older local demo catalogue. The
export contains 26 uploaded files; all original file bytes survived the isolated
2.5 upgrade, and all 15 distinct media references resolve. Export checksums and
the media verification report are stored beside the private backup. The temporary
Railway SSH key used for export has been revoked.

Retain the old deployment's commit/image and variable configuration. Do not
redeploy the old 2.4 application against a schema upgraded to 2.5. Rollback uses
its matching original database and media snapshot. After any new orders on the
new site, reconcile those writes before rolling traffic back.

## Database rehearsal

1. Restore the MySQL dump and uploads into a separate, disposable environment.
   Never test migrations against the live Railway database.
2. Run Bagisto 2.5 migrations on that restored MySQL database. The upstream upgrade
   renames media directories and updates their references, so migrate the media
   copy with the database. The compatibility migration retains the legacy campaign
   `type` and `mail_to` values omitted from a fresh upstream 2.5 schema.
3. Create an empty PostgreSQL database and run the same application migrations.
   Use Neon's direct endpoint for this step. In the production image:

   ```sh
   /usr/local/bin/classy-migrate.sh
   ```

4. Set `DB_SOURCE_URL` to the upgraded MySQL copy and `DB_URL` to the direct
   PostgreSQL endpoint. Validate, then transfer:

   ```sh
   php artisan classy:transfer-database --replace-schema-defaults
   php artisan classy:transfer-database --replace-schema-defaults --execute
   ```

The transfer refuses nonempty business tables and unknown columns. The explicit
schema-default option permits replacing only `attributes` and `marketing_events`
rows inserted by fresh migrations. The transfer preserves the target migration
history, copies source IDs and values, converts validated booleans, defers and then
validates foreign keys, verifies counts/password hashes/monetary totals, and resets
PostgreSQL sequences. Any import failure rolls back the target transaction.

Use `DB_URL` with Neon's **pooled** connection for application requests and
`DB_DIRECT_URL` with its **direct** connection for migrations. PostgreSQL defaults
to `DB_SSLMODE=require`; production startup verifies that its actual connection
uses TLS. Keep the same underlying application key as Railway.

## Durable media

Keep `FILESYSTEM_DISK=public`. Set both `PUBLIC_FILESYSTEM_DRIVER=cloudinary` and
`PRIVATE_FILESYSTEM_DRIVER=cloudinary`; existing public/private disk names remain
valid for every Bagisto writer. Public originals use raw uploads with their full
path and extension. Private originals use authenticated delivery and never receive
a public URL. Public originals are converted into resized images by Bagisto.
Cloudinary Admin API limits also apply to existence checks and paginated exports.

Before copying business files, exercise the real account:

```sh
php artisan classy:check-storage public
php artisan classy:check-storage private
```

Set `MEDIA_SOURCE_PATH` to the upgraded local public media copy, then:

```sh
php artisan classy:copy-media media_source public
php artisan classy:copy-media media_source public --execute
```

Repeat for private files with `MEDIA_SOURCE_PATH` pointing at the private copy and
`private` as the target. The default command validates existing copies; `--execute`
copies missing originals. Conflicting target content causes a failure and leaves
the source intact. Every copied file is checked with SHA-256. Audit database references:

```sh
php artisan classy:audit-media --http
```

Do not regenerate placeholders to mask missing customer uploads. Image repair and
photo import are explicit operator commands, use the public disk abstraction, and
write 2.5 directory names. Placeholder repair additionally requires
`--allow-placeholders`. Redeploy and confirm uploaded originals remain available.

## Render and email

Deploy staging only after database import and real storage checks pass. Set the
actual Render service URL as `APP_URL`, keep database-backed sessions and synchronous
jobs, and use `MAIL_MAILER=brevo` with a verified sender. The configured transport
uses HTTPS rather than SMTP. Confirm password-reset delivery and order emails with
real recipients before launch. No mail credentials are baked into the image.

`MIGRATE_ON_BOOT=false` and `INITIALIZE_STORE=false` prevent routine startup from
migrating, seeding, changing branding, repairing images, or replacing accounts.
Run controlled migrations through the direct endpoint before starting the service.
Do not enable initial-store seeders for a migrated shop.

Verify catalogue/search/filters, variants, customer/admin/worker access, checkout,
payment verification and callbacks, reports, uploaded-image delivery, and email.
Render health checks use `/up`; application startup also checks database readiness.
No artificial keep-alive traffic is configured. Startup verifies the imported schema,
administrator, and channel before creating the disposable `storage/installed` marker.
This disables the upstream browser installer without embedding a `.env` file.

## Independent backup and restore

Run the operator scripts with PostgreSQL clients at least as new as the server
(PostgreSQL 17 for this Neon project; the verified operator uses client 18).
Render Free has no operator shell, so run backups independently from Render.

Export each Cloudinary disk into a separate local directory using `classy:copy-media`,
with `media_export` as the target and `MEDIA_EXPORT_PATH` set respectively to
`/backup/media/public` and `/backup/media/private`. Verify those exports, then run:

```sh
DB_CONNECTION=pgsql DB_HOST=DIRECT_HOST DB_PORT=5432 DB_SSLMODE=require \
DB_DATABASE=DATABASE DB_USERNAME=USER DB_PASSWORD=SECRET \
MEDIA_DIR=/backup/media MEDIA_EXPORT_COMPLETE=true scripts/backup.sh
```

Supply real secrets through the process environment; the values above are placeholders.
Backups use PostgreSQL custom-format dumps, media archives, checksums, and a completion
marker. Failed exports are never marked complete. Retention is manual so migration
snapshots cannot be pruned accidentally. Copy completed backups off this machine.

`scripts/restore.sh backups/STAMP` requires matching database-driver settings, an
empty target database, and an empty media destination. It verifies checksums and
rejects unsafe archive entries before database restoration. PostgreSQL restoration
uses one transaction and stops on the first error. Rehearse restoration regularly.

## Validation record

Local rehearsals use isolated PHP 8.4/MySQL/PostgreSQL containers. A PostgreSQL
backup/restore drill compared every row checksum and sequence state across
144 tables and 5,263 rows. The live Railway SQL/media export has also been restored
and upgraded separately. Both Tailwind 4 theme builds pass. Backup/restore guard
tests and storage adapter contract tests pass.

The complete custom suite passes on both PostgreSQL and MySQL: 143 tests and
476 assertions per database. All seven
backup safeguard tests, Pint, Composer validation, shell syntax checks, and the
translation check pass. The production Docker image starts with PostgreSQL TLS
required under a 512 MB memory limit; readiness rejects `sslmode=disable`.
Chromium checks at 1440 and 390 pixels pass for the homepage, customer login,
admin login, and health endpoint, with no JavaScript or local asset errors.

Stored footer links still contain the source Railway origin. Update their
hostname through the admin theme settings when the staging hostname is assigned,
and verify these links and the channel hostname before cutover.

Remote Neon TLS/pooling, real Cloudinary operations, Render deployment/redeployment,
real email delivery, and the final write freeze/domain/callback cutover remain
unverified until provider access and staging acceptance are complete.

## Remote verification record

The migration uses the new Free Neon project `round-fire-56963406` in
`aws-us-west-2` and the existing Free Render service `srv-db0vk0mgekts73bga2dg`
in Oregon. Existing Neon projects were preserved. The code is on
`deploy/render-neon-20261102`; `main` and the Railway deployment were not changed.

The actual Neon import reconciled the selected source counts, administrator and
customer password hashes, foreign keys, monetary totals, and serial sequences.
The database occupies about 15 MB. The independent remote backup at
`backups/neon-independent/20261102T091100Z` restored successfully into isolated
PostgreSQL with TLS: 144 tables, 1,424 rows, 118 sequences, and 26 uploaded files
match by per-table row checksums, sequence state, and file SHA-256. Compare hash
results by table name because SQL does not guarantee UNION ALL result order.

Real Cloudinary public and authenticated-private storage contract checks pass.
All 26 public files (2,225,204 bytes) were copied and independently exported with
matching hashes; no private business files were present. All 15 distinct database
media references pass HTTPS checks against Cloudinary and the imported Neon data.

Only hosting configuration was rebased after reconciliation: the default channel
hostname, stored footer links, and the configured email sender. Business data,
identities, passwords, and order values were retained. A fresh production container
without a `.env` file or uploaded-file volume passes desktop/mobile browser tests
and administrator login, orders, reports, stock, payments, and storefront settings
using the preserved credentials. Three startup-protection tests also pass.
