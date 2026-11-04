#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
STAGE="$(mktemp -d)"
OUT="$ROOT/rubiarocha-cpanel.zip"

cleanup() {
    rm -rf "$STAGE"
}
trap cleanup EXIT

cd "$ROOT"

if [[ ! -d node_modules ]]; then
    npm ci
fi

npm run build

rsync -a \
    --exclude '.git' \
    --exclude '.cursor' \
    --exclude '.claude' \
    --exclude '.agents' \
    --exclude '.factory' \
    --exclude '.junie' \
    --exclude '.grok' \
    --exclude '.idea' \
    --exclude '.vscode' \
    --exclude '.nova' \
    --exclude '.zed' \
    --exclude '.codex' \
    --exclude '.phpunit.cache' \
    --exclude '.phpunit.result.cache' \
    --exclude 'node_modules' \
    --exclude 'vendor' \
    --exclude 'tests' \
    --exclude 'docker' \
    --exclude '.env' \
    --exclude '.env.backup' \
    --exclude '.env.production' \
    --exclude 'public/hot' \
    --exclude 'public/storage' \
    --exclude 'storage/logs' \
    --exclude 'storage/pail' \
    --exclude 'storage/framework/cache/data' \
    --exclude 'storage/framework/sessions' \
    --exclude 'storage/framework/views' \
    --exclude 'storage/framework/testing' \
    --exclude 'rubiarocha-cpanel.zip' \
    --exclude 'rubiarocha-cpanel.sql' \
    --exclude 'auth.json' \
    --exclude 'database/*.sqlite' \
    --exclude 'database/*.sqlite-journal' \
    --exclude 'storage/app/private/backups' \
    "$ROOT/" "$STAGE/"

cd "$STAGE"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

ln -sfn ../storage/app/public public/storage

DENIED='<IfModule mod_authz_core.c>
    Require all denied
</IfModule>'

printf '%s\n' "$DENIED" > vendor/.htaccess
printf '%s\n' "$DENIED" > storage/logs/.htaccess
printf '%s\n' "$DENIED" > storage/framework/.htaccess
printf '%s\n' "$DENIED" > storage/app/private/.htaccess

rm -f "$OUT"
zip -r -y -q "$OUT" .

echo "Pacote: $OUT"
