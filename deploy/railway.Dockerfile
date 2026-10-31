# Railway: repository root is the build context.
FROM php:8.4-apache-bookworm AS app

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# System deps, PHP extensions Bagisto needs, composer, Node 22 (shop assets).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates gnupg \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev libxml2-dev libcurl4-openssl-dev libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        intl bcmath calendar gd zip pdo_mysql mysqli soap opcache exif pcntl mbstring curl \
    && a2enmod rewrite headers \
    && mkdir -p /usr/share/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && php -m | grep -E "^(intl|gd|pdo_mysql|bcmath|calendar|soap|zip)$"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Pinned Bagisto core (matches local dev: v2.4.12, same composer.lock).
RUN composer create-project bagisto/bagisto /var/www/html "2.4.12" \
        --no-interaction --prefer-dist --no-dev

# Build from the deployed checkout, not the tip of a remote branch.
COPY store/ /tmp/overlay/
RUN cp -a /tmp/overlay/. /var/www/html/ && rm -rf /tmp/overlay
RUN composer config --unset php-binary

# Autoload our namespace, install our extra deps (activity log) from the
# overlaid lock file, rebuild shop assets.
# Bagisto modules this shop does not use (other payment gateways, social login)
# are not in composer.json/providers, so drop their source before autoloading.
RUN for m in Stripe Razorpay PayU PayGlocal PhonePe Paypal SocialLogin; do rm -rf "packages/Webkul/$m"; done \
    && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && cd packages/Webkul/Shop && npm install --no-audit --no-fund && npm run build \
    && rm -rf /var/www/html/packages/Webkul/Shop/node_modules

COPY deploy/php-production.ini /usr/local/etc/php/conf.d/classy.ini
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/classy-entrypoint.sh
RUN chmod +x /usr/local/bin/classy-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["classy-entrypoint.sh"]
CMD ["apache2-foreground"]
