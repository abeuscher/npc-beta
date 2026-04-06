#!/bin/sh
# Fix ownership on volume-mounted directories before starting.
# Named volumes are mounted after the image is built, so Dockerfile chown
# does not persist across container restarts.

chown -R www-data:www-data /var/www/html/storage \
                           /var/www/html/bootstrap/cache \
                           /var/www/html/public/build 2>/dev/null || true

# Drop to www-data and exec the CMD
exec gosu www-data "$@"
