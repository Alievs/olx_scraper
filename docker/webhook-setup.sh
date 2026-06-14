#!/bin/sh
set -e

echo "Waiting for ngrok tunnel..."
sleep 6

URL=$(php -r "
\$data = json_decode(file_get_contents('http://ngrok:4040/api/tunnels'), true);
foreach (\$data['tunnels'] ?? [] as \$t) {
    if (str_starts_with(\$t['public_url'], 'https')) {
        echo \$t['public_url'];
        exit;
    }
}
")

if [ -z "$URL" ]; then
    echo "ERROR: Could not get ngrok URL. Check NGROK_AUTHTOKEN."
    exit 1
fi

echo "Ngrok URL: $URL"
php artisan telegram:set-webhook "$URL"
