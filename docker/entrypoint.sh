#!/bin/sh
# Fix ownership on volume-mounted directories before starting.
# Named volumes are mounted after the image is built, so Dockerfile chown
# does not persist across container restarts.
# PHP-FPM must start as root (to open logs and bind sockets), then drops
# to www-data itself via pool config — so we do NOT use gosu here.

chown -R www-data:www-data /var/www/html/storage \
                           /var/www/html/bootstrap/cache \
                           /var/www/html/public/build 2>/dev/null || true

# Discard any compiled package/service manifest carried over on the persistent
# bootstrap_cache volume — it can reference providers/classes this image no
# longer ships (e.g. a removed package), which fatals at bootstrap for web AND
# artisan alike. Laravel regenerates both from THIS image's vendor on next boot.
# (Session 342 removed laravel-debugbar; a stale manifest naming its provider
# bricked the 0.345.06 upgrade.)
rm -f /var/www/html/bootstrap/cache/packages.php \
      /var/www/html/bootstrap/cache/services.php 2>/dev/null || true

exec "$@"
