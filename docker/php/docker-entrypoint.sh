#!/bin/sh
set -e

# Set permissions for volumes
if [ -d /var/www/api/var ] && [ "$(stat -c '%u' /var/www/api/var)" = '0' ]; then
    chown -R www-data:www-data /var/www/api/var
fi

if [ -d /var/www/api/vendor ] && [ "$(stat -c '%u' /var/www/api/vendor)" = '0' ]; then
    chown -R www-data:www-data /var/www/api/vendor
fi

# Execute the main command (e.g., "php-fpm")
exec "$@"
