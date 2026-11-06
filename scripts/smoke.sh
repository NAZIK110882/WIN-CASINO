#!/usr/bin/env bash
# Smoke-тести API після деплою/в CI.
# Використання: CASINO_PASSWORD=*** [ALLOWED_ORIGIN=https://app.example.com] scripts/smoke.sh http://localhost:8000/api/v1
set -euo pipefail

API="${1:-http://localhost:8000/api/v1}"
PASS="${CASINO_PASSWORD:?set CASINO_PASSWORD}"
USER_NAME="${CASINO_USER:-player1}"

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "ok:   $*"; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

[ "$(code "$API/health")" = 200 ] && pass "health 200" || fail "health"
[ "$(code "$API/user")" = 401 ] && pass "no token -> 401" || fail "user without token must be 401"

TOKEN=$(curl -s -X POST "$API/login" -H 'Content-Type: application/json' \
  -d "{\"username\":\"$USER_NAME\",\"password\":\"$PASS\"}" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
[ -n "$TOKEN" ] && pass "login -> token" || fail "login"

AUTH=(-H "Authorization: Bearer $TOKEN")
[ "$(code "${AUTH[@]}" "$API/user")" = 200 ] && pass "user 200" || fail "user with token"
[ "$(code "${AUTH[@]}" "$API/admin_stats")" = 403 ] && pass "player -> admin_stats 403" || fail "RBAC"
[ "$(code -X POST "${AUTH[@]}" -H 'Content-Type: application/json' -d '{"bet":-5}' "$API/spin")" = 422 ] \
  && pass "invalid bet -> 422" || fail "input validation"
[ "$(code -X POST "$API/login" -H 'Content-Type: application/json' -d '{"username":"x","password":"y"}')" = 422 ] \
  && pass "invalid login payload -> 422" || fail "login validation"

if curl -s -D - -o /dev/null -H "Origin: https://evil.example" "$API/health" | grep -qi '^access-control-allow-origin'; then
  fail "CORS leaks to foreign origin"
fi
pass "CORS blocked for foreign origin"

if [ -n "${ALLOWED_ORIGIN:-}" ]; then
  curl -s -D - -o /dev/null -H "Origin: $ALLOWED_ORIGIN" "$API/health" \
    | grep -qi "^access-control-allow-origin: $ALLOWED_ORIGIN" && pass "CORS allowed for $ALLOWED_ORIGIN" || fail "CORS for frontend"
fi
echo "ALL SMOKE TESTS PASSED"
