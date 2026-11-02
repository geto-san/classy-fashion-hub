# Render: repository root is the build context; build only this checkout.
FROM php:8.4-apache-bookworm AS app

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# System deps, PHP extensions Bagisto needs, composer, Node 22 (shop assets).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates gnupg \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
        libonig-dev libxml2-dev libcurl4-openssl-dev libssl-dev libpq-dev postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        intl bcmath calendar gd zip pdo_mysql pdo_pgsql mysqli soap opcache exif pcntl mbstring curl \
    && a2enmod rewrite headers \
    && mkdir -p /usr/share/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && php -m | grep -E "^(intl|gd|pdo_mysql|pdo_pgsql|bcmath|calendar|soap|zip)$"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install the pinned upstream skeleton before applying the reviewed customizations.
RUN composer create-project bagisto/bagisto /var/www/html "2.5.0" \
        --no-interaction --prefer-dist --no-install --no-scripts

# Build from the deployed checkout, not the tip of a remote branch.
COPY store/ /tmp/overlay/
RUN test -f /tmp/overlay/config/filesystems.php \
    && test -f /tmp/overlay/config/database.php \
    && test -f /tmp/overlay/packages/ClassyFashion/src/Filesystem/CloudinaryFilesystem.php \
    && test -f /tmp/overlay/packages/Webkul/Admin/src/Routes/web/catalog-routes.php \
    && test ! -f /tmp/overlay/.env \
    && cp -a /tmp/overlay/. /var/www/html/ && rm -rf /tmp/overlay
RUN composer config --unset php-binary

# Autoload our namespace, install our extra deps (activity log) from the
# overlaid lock file, rebuild shop assets.
# Bagisto modules this shop does not use (other payment gateways, social login)
# are not in composer.json/providers, so drop their source before autoloading.
COPY deploy/build-assets.sh /usr/local/bin/classy-build-assets.sh
RUN for m in Stripe Razorpay PayU PayGlocal PhonePe Paypal SocialLogin; do rm -rf "packages/Webkul/$m"; done \
    && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && sh /usr/local/bin/classy-build-assets.sh /var/www/html \
    && rm -rf /var/www/html/packages/Webkul/Shop/node_modules /var/www/html/packages/Webkul/Admin/node_modules

COPY deploy/php-production.ini /usr/local/etc/php/conf.d/classy.ini
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY deploy/database-ready.php /usr/local/bin/classy-database-ready.php
COPY deploy/migrate.sh /usr/local/bin/classy-migrate.sh
COPY deploy/entrypoint.sh /usr/local/bin/classy-entrypoint.sh
COPY deploy/serve.sh /usr/local/bin/classy-serve.sh
RUN chmod +x /usr/local/bin/classy-entrypoint.sh /usr/local/bin/classy-migrate.sh /usr/local/bin/classy-serve.sh

ENV PGCONNECT_TIMEOUT=5

EXPOSE 80

ENTRYPOINT ["classy-entrypoint.sh"]
CMD ["classy-serve.sh"]
