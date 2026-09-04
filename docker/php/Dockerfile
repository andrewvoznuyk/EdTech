FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache git acl bash vim

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl zip opcache bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY docker/php/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]


FROM base AS dev

RUN install-php-extensions xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

FROM base AS prod

ENV APP_ENV=prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/app-prod.ini

COPY api/composer.json api/composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY api/ .

RUN composer dump-autoload --no-dev --classmap-authoritative && \
    composer run-script post-install-cmd && \
    php bin/console cache:warmup && \
    chmod -R 777 var/