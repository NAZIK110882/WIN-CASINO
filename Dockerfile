# syntax=docker/dockerfile:1.7

# ---------- 1) залежності Composer (без dev) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY src ./src
RUN composer dump-autoload --no-dev --classmap-authoritative

# ---------- 2) базовий PHP+Apache ----------
FROM php:8.3-apache AS base
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev libpq-dev \
 && docker-php-ext-install mbstring pdo_mysql pdo_pgsql opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && a2enmod rewrite headers \
 && apt-get purge -y --auto-remove libonig-dev \
 && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html

# ---------- 3) profiling (Xdebug) — лише для завдання 4 ----------
FROM base AS profiling
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY --from=vendor /app/vendor ./vendor
COPY api ./api
COPY src ./src
COPY scripts ./scripts

# ---------- 4) production (остання стадія = дефолтна) ----------
FROM base AS production
COPY --from=vendor /app/vendor ./vendor
COPY api ./api
COPY src ./src
COPY scripts ./scripts
COPY docker/render-start.sh /usr/local/bin/render-start
COPY index.html login.html slots.html bonanza.html rocket.html casino-api.js config.js ./
COPY docker/render-config.js ./config.js
COPY *.jpg *.png *.mp3 *.mp4 ./
RUN chmod 755 /usr/local/bin/render-start \
 && mkdir -p /var/cache/casino \
 && chown -R www-data:www-data /var/cache/casino
ENV CACHE_DIR=/var/cache/casino
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/api/v1/health.php") === false ? 1 : 0);'
