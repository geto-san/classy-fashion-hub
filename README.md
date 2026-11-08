# Classy Fashion Hub

A fashion-shop coursework project built on **Bagisto 2.5 / Laravel 13 / PHP 8.4**,
with the **Ethereal Fashion** storefront.

[Open shop](https://classy-fashion-hub.onrender.com) ·
[Admin login](https://classy-fashion-hub.onrender.com/admin/login)

## Explore the project

- **122 African styles, 24 collections:** 22 collections of five and two of six, with product-only photographs.
- **Shopping:** search, categories, cart, checkout, accounts and order tracking.
- **Administration:** products, orders, delivery, staff permissions, reports and homepage editing.
- **Coursework mode:** approve/decline a practice mobile-money payment without transferring money;
  notification previews stay in private application logs; the assistant answers locally.

Accounts, inventory, orders and reports use the actual database. Prices, costs and
stock are authored classroom scenarios, not supplier offers. The catalogue has
ordinary retail presentation without demonstration labels. Retailer references and
photo reviews are in the [African catalogue manifest](store/packages/ClassyFashion/src/Resources/african-catalogue/manifest.json).
Images reference Jumia Kenya, Ghana and Nigeria, and The Kikoy Co.; they are not
represented as openly licensed or as evidence of a supplier relationship.

Hosted on **Render + Neon + Cloudinary**. See the [current report](docs/DEPLOYMENT_REPORT.md)
for verification and the distinction between coursework and commercial operation.

## Run locally

Install PHP **8.4.1+**, Composer 2, Node.js 22+ and MySQL 8/MariaDB.
Use an empty database: installation is for a fresh local environment.

```bash
git clone https://github.com/geto-san/classy-fashion-hub.git
cd classy-fashion-hub
scripts/setup-local.sh
```

Edit `store/.env`: database credentials, `APP_URL=http://localhost:8000`, and
`CLASSY_COURSEWORK_MODE=true`. Leave paid gateway, SMTP and AI credentials empty.
Then:

```bash
cd store
php artisan bagisto:install --no-interaction
php artisan db:seed --class='Database\Seeders\ClassyFashionSeeder'
cd ..
python3 scripts/fetch-coursework-media.py
cd store
php artisan storage:link
php artisan classy:install-african-catalogue
php artisan classy:install-african-catalogue --execute
php artisan serve --port=8000
```

Open `http://localhost:8000`; administration is at `/admin/login`. After the first
photo download, local presentations do not require paid external services.
Repeat catalogue imports preserve already-installed listings and client edits.

## Manage it

| Task | Location |
|---|---|
| Add/edit products, photos, prices and stock | Catalog → Products |
| Edit homepage photos and copy | Storefront settings |
| Give the client their own administrator | Settings → Users; client changes password in My Account |
| Manage orders and delivery | Sales → Orders |
| Review sales, stock and profit | Reports |

Never commit populated `.env` files or passwords. [Account and operator guide](docs/USER_GUIDE.md).

## Code and checks

| Path | Purpose |
|---|---|
| `store/packages/ClassyFashion/` | Custom features, storefront, translations and catalogue |
| `store/packages/Webkul/` | Selected Bagisto overrides |
| `store/tests/` | Application regression tests |
| `deploy/` · `render.yaml` | Hosting and Docker build |
| `scripts/` | Setup, media download and recovery tools |
| `docs/` | Guides; `archive/` contains historical records |

The setup script installs pinned Bagisto core beneath the customizations.
Run tests against a separate, seeded local test database:

```bash
cd store
php vendor/bin/pest --testsuite="Classy Fashion"
php artisan bagisto:translations:check
```

[Deployment/recovery](docs/DEPLOY.md) · [Presentation guide](docs/DEMO_SCRIPT.md) ·
[Future commercial launch plan](docs/CLIENT_LAUNCH_PLAN.md)
