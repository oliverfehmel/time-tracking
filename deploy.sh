#!/bin/bash
#Purpose = Deploy new commits of project to plesk with PHP 8.5; You can adapt this to your hosting as you wish
#Created on 06.06.2019
#Last Updated 02.03.2026
#Author = Oliver Fehmel
#Version 1.2.5

set -euo pipefail

PHP="/opt/plesk/php/8.5/bin/php"
COMPOSER="$PHP /usr/lib/plesk-9.0/composer.phar"

echo "==> Git pull"
git pull

echo "==> Composer install (prod)"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Clear cache (prod) + warmup"
$PHP bin/console cache:clear --env=prod
$PHP bin/console cache:warmup --env=prod

echo "==> Migrations"
$PHP bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "==> Compile asset map"
$PHP bin/console asset-map:compile --env=prod

echo "==> Done"
