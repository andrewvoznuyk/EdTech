FROM dunglas/frankenphp:1-php8.4-alpine AS base

RUN apk add --no-cache git acl bash vim

RUN install-php-extensions pdo_mysql intl zip opcache bcmath gd
RUN install-php-extensions amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY docker/php/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini
COPY docker/php/Caddyfile /etc/caddy/Caddyfile
COPY docker/php/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]


FROM base AS dev

RUN install-php-extensions xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

FROM base AS prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/app-prod.ini

COPY api/composer.json api/composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY api/ .

RUN composer dump-autoload --no-dev --classmap-authoritative && \
    composer run-script post-install-cmd && \
    php bin/console cache:warmup && \
    chmod -R 777 var/