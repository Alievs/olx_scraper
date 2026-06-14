#!/bin/sh
set -e

# Bind-mount (.:/var/www/html) overrides image-layer ownership with host uid,
# so storage/ and bootstrap/cache must be made writable by php-fpm (www-data)
# at runtime, after the mount is in place.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec "$@"
