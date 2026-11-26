#!/bin/sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
PORT=18765
TMP=$(mktemp -d)
COOKIE="$TMP/cookies.txt"
IMAGE="$TMP/pixel.png"
SERVER_LOG="$TMP/php-server.log"
export GLOBALFIND_DATA_DIR="$TMP/data"
export GLOBALFIND_ADMIN_PASSWORD='test-admin-password-only'

cleanup() {
  if [ -n "${SERVER_PID:-}" ]; then kill "$SERVER_PID" 2>/dev/null || true; fi
  rm -rf "$TMP"
}
trap cleanup EXIT INT TERM

for file in "$ROOT/backend/config.php" "$ROOT/backend/database.php" "$ROOT/backend/api.php" "$ROOT/tests/router.php"; do
  php -l "$file"
done

php -S "127.0.0.1:$PORT" "$ROOT/tests/router.php" >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!
ready=0
for attempt in $(seq 1 40); do
  if curl -fsS "http://127.0.0.1:$PORT/healthz" >/dev/null; then ready=1; break; fi
  sleep 0.25
done
if [ "$ready" -ne 1 ]; then cat "$SERVER_LOG"; exit 1; fi

curl -fsS "http://127.0.0.1:$PORT/" | grep -q 'GLOBAL'
curl -fsS "http://127.0.0.1:$PORT/admin" | grep -q 'Admin sign in'
PRODUCTS=$(curl -fsS "http://127.0.0.1:$PORT/api/products")
printf '%s' "$PRODUCTS" | php -r '$x=json_decode(stream_get_contents(STDIN), true); if (!is_array($x) || count($x) !== 20) exit(1);'

STATUS=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/api/admin/products")
[ "$STATUS" = 401 ]
curl -fsS -c "$COOKIE" -H 'Content-Type: application/json' -d '{"password":"test-admin-password-only"}' "http://127.0.0.1:$PORT/api/login" | grep -q '"ok":true'
ADMIN_PRODUCTS=$(curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/api/admin/products")
printf '%s' "$ADMIN_PRODUCTS" | php -r '$x=json_decode(stream_get_contents(STDIN), true); if (!is_array($x) || count($x) !== 20) exit(1);'

CREATED=$(curl -fsS -b "$COOKIE" -H 'Content-Type: application/json' -d '{"name":"PHP smoke test item","category":"shoes","price":12.5,"badge":"TEST","image":"https://example.com/item.jpg","supplier_url":"https://example.com/product","active":true}' "http://127.0.0.1:$PORT/api/admin/products")
ID=$(printf '%s' "$CREATED" | php -r '$x=json_decode(stream_get_contents(STDIN), true); echo (int)($x["id"] ?? 0);')
[ "$ID" -gt 0 ]
curl -fsS -b "$COOKIE" -X PUT -H 'Content-Type: application/json' -d '{"name":"Updated PHP smoke item","category":"accessories","price":19.9,"badge":"UPDATED","image":"https://example.com/item.jpg","supplier_url":"https://example.com/product","active":false}' "http://127.0.0.1:$PORT/api/admin/products/$ID" | grep -q '"ok":true'
curl -fsS -b "$COOKIE" -X DELETE "http://127.0.0.1:$PORT/api/admin/products/$ID" | grep -q '"ok":true'

printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5ZkAAAAASUVORK5CYII=' | base64 -d > "$IMAGE"
UPLOAD=$(curl -fsS -b "$COOKIE" -F "image=@$IMAGE;type=image/png" "http://127.0.0.1:$PORT/api/admin/upload")
IMAGE_URL=$(printf '%s' "$UPLOAD" | php -r '$x=json_decode(stream_get_contents(STDIN), true); echo $x["url"] ?? "";')
case "$IMAGE_URL" in /uploads/*.png) ;; *) echo "Unexpected upload URL: $IMAGE_URL"; exit 1;; esac
curl -fsS "http://127.0.0.1:$PORT$IMAGE_URL" >/dev/null

STATUS=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/backend/data/products.sqlite")
[ "$STATUS" = 404 ]
curl -fsS -b "$COOKIE" -X DELETE "http://127.0.0.1:$PORT/api/logout" | grep -q '"ok":true'
STATUS=$(curl -sS -b "$COOKIE" -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/api/admin/products")
[ "$STATUS" = 401 ]

echo 'PHP smoke checks passed.'
