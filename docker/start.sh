#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${APP_KEY:-}" || -z "${LEAGUE_ADMIN_PASSWORD:-}" ]]; then
  echo 'APP_KEY and LEAGUE_ADMIN_PASSWORD are required.' >&2
  exit 1
fi
if [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:${APP_KEY}"
fi
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:10000}}"

port="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${port}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p "$(dirname "${DB_DATABASE:-/var/data/database.sqlite}")"
touch "${DB_DATABASE:-/var/data/database.sqlite}"
chown -R www-data:www-data "$(dirname "${DB_DATABASE:-/var/data/database.sqlite}")" storage bootstrap/cache

runuser -u www-data -- php artisan migrate --force
runuser -u www-data -- php artisan db:seed --force
runuser -u www-data -- php artisan mleague:update || echo 'Initial score update failed; hourly retry remains active.' >&2

runuser -u www-data -- php artisan schedule:work &
scheduler_pid=$!
apache2-foreground &
apache_pid=$!
trap 'kill "$scheduler_pid" "$apache_pid" 2>/dev/null || true' TERM INT
wait -n "$scheduler_pid" "$apache_pid"
exit 1
