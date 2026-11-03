# Classy Fashion Hub

An online fashion shop for Uganda, built with **Bagisto 2.5, Laravel 13, PHP 8.4,
and Tailwind 4**.

[Open shop](https://classy-fashion-hub.onrender.com) ·
[Admin login](https://classy-fashion-hub.onrender.com/admin/login)

## What it does

- Product catalogue with size/colour variants, UGX prices, and stock control.
- Customer accounts, checkout, order tracking, and delivery management.
- Cash on Delivery and configurable mobile-money payments.
- Administrator/worker roles, sales and profit reports, audit logs, and a shop assistant.

Hosting: **Render + Neon PostgreSQL + Cloudinary**, with Brevo for email.
Email delivery, policy pages, and live payments still need launch checks;
see the [handover report](docs/DEPLOYMENT_REPORT.md).

## Run locally

Requires PHP **8.4.1+**, Composer 2, Node.js 22+, and an empty MySQL 8/MariaDB database.
The repository contains our customizations; the setup script installs the Bagisto core.

```bash
git clone https://github.com/geto-san/classy-fashion-hub.git
cd classy-fashion-hub
scripts/setup-local.sh
```

Edit `store/.env` with your database credentials and `APP_URL=http://localhost:8000`, then:

```bash
cd store
php artisan bagisto:install --no-interaction
php artisan db:seed --class='Database\Seeders\ClassyFashionSeeder'
php artisan serve --port=8000
```

Shop: `http://localhost:8000` · Admin: `http://localhost:8000/admin/login`.
For optional demo products, run `php artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder'`.
Use installers and demo seeders only on a fresh local database.

For client testing, use the [100-product demo manifest](store/packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json).
It contains original descriptions, illustrative Jumia category prices, and 100 distinct CC0 photos with source and licence records.
Upload the matching originals to the public storage paths in the manifest, then run:

```bash
php artisan classy:seed-demo-catalogue packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json
# After checking the preview and disabling backorders in inventory settings:
php artisan classy:seed-demo-catalogue packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json --execute
```

Demo listings are visible, labelled `[DEMO]`, and have zero stock. Repeat imports preserve existing products and client edits.
Before opening for sales, delete the `CFH-DEMO-20261103-` listings in Catalog → Products and add the shop's real products, prices, sizes and stock.

## Manage the shop

- **Products:** Catalog → Products → Create Product.
- **Homepage:** Storefront settings → upload photos and edit copy.
- **Staff:** Settings → Users. An existing administrator creates the client's account;
  the client sets their password under My Account.

Production credentials stay private. See the [user guide](docs/USER_GUIDE.md).

## Code layout

| Path | Purpose |
|---|---|
| `store/packages/ClassyFashion/` | Custom features, views, translations, and migrations |
| `store/packages/Webkul/` | Selected Bagisto overrides |
| `store/database/` · `store/tests/` | Seeders, migrations, and application tests |
| `deploy/` · `render.yaml` | Docker build and hosting configuration |
| `scripts/` | Local setup, backups, restore, and verification |
| `brand-kit/` | Brand source assets |
| `docs/` | Current guides; `archive/` holds historical notes and `project/` holds requirements |

## Check changes

Run against a separate, seeded local test database:

```bash
cd store
php vendor/bin/pest --testsuite="Classy Fashion"
```

After rebuilding a theme, clear its caches:

```bash
php artisan optimize:clear
php artisan responsecache:clear
```

Deployment and recovery: [deployment guide](docs/DEPLOY.md).
Local demonstration: [demo guide](docs/DEMO_SCRIPT.md).

Client testing and a free-first launch checklist: [client launch plan](docs/CLIENT_LAUNCH_PLAN.md).
