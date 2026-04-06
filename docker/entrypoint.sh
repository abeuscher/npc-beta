#!/bin/sh
# Fix ownership on volume-mounted directories before starting.
# Named volumes are mounted after the image is built, so Dockerfile chown
# does not persist across container restarts.
# PHP-FPM must start as root (to open logs and bind sockets), then drops
# to www-data itself via pool config — so we do NOT use gosu here.

chown -R www-data:www-data /var/www/html/storage \
                           /var/www/html/bootstrap/cache \
                           /var/www/html/public/build 2>/dev/null || true

exec "$@"
