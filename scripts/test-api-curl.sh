#!/usr/bin/env bash
# Run against a live server: php artisan migrate --seed && php artisan serve
#   ./scripts/test-api-curl.sh
# Optional: BASE_URL=http://127.0.0.1:8000 ./scripts/test-api-curl.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
BASE_URL="${BASE_URL%/}"

REGISTER_EMAIL="curltest_$(date +%s)@example.com"

echo_step() {
  echo ""
  echo "============================================================================"
  echo "$1"
}

echo_step "1. POST ${BASE_URL}/api/auth/register"
RESP1=$(curl -sS -w "\n%{http_code}" -X POST "${BASE_URL}/api/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "{\"company_name\":\"Curl Test Co\",\"email\":\"${REGISTER_EMAIL}\",\"password\":\"Password1!\",\"password_confirmation\":\"Password1!\",\"phone\":\"+10000000002\"}")
HTTP1=$(echo "$RESP1" | tail -n1)
BODY1=$(echo "$RESP1" | sed '$d')
echo "HTTP ${HTTP1}"
echo "$BODY1" | python3 -m json.tool 2>/dev/null || echo "$BODY1"

echo_step "2. POST ${BASE_URL}/api/auth/login"
RESP2=$(curl -sS -w "\n%{http_code}" -X POST "${BASE_URL}/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@loomsolutions.com","password":"admin123"}')
HTTP2=$(echo "$RESP2" | tail -n1)
BODY2=$(echo "$RESP2" | sed '$d')
echo "HTTP ${HTTP2}"
echo "$BODY2" | python3 -m json.tool 2>/dev/null || echo "$BODY2"

TOKEN=$(echo "$BODY2" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])" 2>/dev/null || true)
if [[ -z "${TOKEN:-}" ]]; then
  echo "ERROR: Could not read data.token from login response. Fix login or DB seed before continuing."
  exit 1
fi

auth_get() {
  local num="$1"
  local path="$2"
  echo_step "${num}. GET ${BASE_URL}${path}"
  local resp
  resp=$(curl -sS -w "\n%{http_code}" -X GET "${BASE_URL}${path}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}")
  local http
  http=$(echo "$resp" | tail -n1)
  local body
  body=$(echo "$resp" | sed '$d')
  echo "HTTP ${http}"
  echo "$body" | python3 -m json.tool 2>/dev/null || echo "$body"
}

auth_get "3" "/api/dashboard"
auth_get "4" "/api/properties"
auth_get "5" "/api/tenants"
auth_get "6" "/api/invoices"
auth_get "7" "/api/payments"
auth_get "8" "/api/maintenance"

echo ""
echo "Done."
