# Production image: Apache + PHP 8.4 (the version CI tests against).
# Build and run it with the compose.yaml next to this file, see README.md → "Quick start with Docker".
FROM php:8.4-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions gd intl opcache pdo_mysql pdo_pgsql zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers

COPY docker/app/php.ini $PHP_INI_DIR/conf.d/zz-time-tracking.ini
COPY docker/app/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/entrypoint.sh /usr/local/bin/time-tracking-entrypoint

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    TZ=UTC \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Dependencies first, so code changes do not invalidate this layer.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console assets:install public \
    && php bin/console asset-map:compile \
    && rm -rf var/cache var/log \
    && mkdir -p var public/uploads public/favicon \
    && chown -R www-data:www-data var public/uploads public/favicon \
    && chmod +x /usr/local/bin/time-tracking-entrypoint

EXPOSE 80

ENTRYPOINT ["time-tracking-entrypoint"]
CMD ["apache2-foreground"]
