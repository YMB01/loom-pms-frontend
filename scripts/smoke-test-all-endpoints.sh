#!/usr/bin/env bash
# Full API smoke test (matches docs/QA endpoint order).
# Prerequisites:
#   cd loom-pms-api && cp .env.example .env && php artisan key:generate
#   Configure DB, then: php artisan migrate:fresh --seed && php artisan storage:link
#   APP_DEBUG=true recommended (portal OTP returns debug_code for verify-otp).
#   Super Admin: set SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD in .env (defaults in .env.example).
#
# Usage:
#   php artisan serve --host=127.0.0.1 --port=8000
#   ./scripts/smoke-test-all-endpoints.sh
#   BASE_URL=http://127.0.0.1:8000 ./scripts/smoke-test-all-endpoints.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
BASE_URL="${BASE_URL%/}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

PASS=0
FAIL=0
TOTAL=0

load_env_var() {
  local key="$1"
  local f="$API_ROOT/.env"
  if [[ -f "$f" ]]; then
    grep -E "^${key}=" "$f" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//'
  fi
}

SUPER_ADMIN_EMAIL="${SUPER_ADMIN_EMAIL:-$(load_env_var SUPER_ADMIN_EMAIL)}"
SUPER_ADMIN_PASSWORD="${SUPER_ADMIN_PASSWORD:-$(load_env_var SUPER_ADMIN_PASSWORD)}"
SUPER_ADMIN_EMAIL="${SUPER_ADMIN_EMAIL:-superadmin@loomsolutions.com}"
SUPER_ADMIN_PASSWORD="${SUPER_ADMIN_PASSWORD:-admin123}"

# req() joins HTTP code and body with "|"; body may be multiline — never use `cut -f1` on the whole string.
http_code_from_r() {
  echo "$1" | head -1 | sed 's/|.*//'
}

json_body_from_r() {
  echo "$1" | sed '1s/^[^|]*|//'
}

# args: method path [json body] [bearer token]
req() {
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local token="${4:-}"
  local url="${BASE_URL}${path}"
  local args=( -sS -X "$method" "$url" -H "Accept: application/json" -H "Content-Type: application/json" )
  if [[ -n "$token" ]]; then
    args+=( -H "Authorization: Bearer ${token}" )
  fi
  local raw
  if [[ "$method" == "GET" || "$method" == "DELETE" ]]; then
    raw=$(curl "${args[@]}" -w "\n%{http_code}" )
  else
    raw=$(curl "${args[@]}" -d "$body" -w "\n%{http_code}" )
  fi
  local http
  http=$(echo "$raw" | tail -n1)
  local resp
  resp=$(echo "$raw" | sed '$d')
  echo "$http|$resp"
}

record() {
  local name="$1"
  local http="$2"
  TOTAL=$((TOTAL + 1))
  if [[ "$http" =~ ^2[0-9][0-9]$ ]]; then
    PASS=$((PASS + 1))
    printf '[OK]   %s → HTTP %s\n' "$name" "$http"
  else
    FAIL=$((FAIL + 1))
    printf '[FAIL] %s → HTTP %s\n' "$name" "$http"
  fi
}

echo "BASE_URL=$BASE_URL"
echo ""

# --- PUBLIC (no auth) ---
R=$(req GET "/api/health")
record "GET /api/health" "$(http_code_from_r "$R")"

# --- AUTH ---
REG_EMAIL="smoke_$(date +%s)@example.com"
R=$(req POST "/api/auth/register" "{\"company_name\":\"Smoke Test Co\",\"email\":\"${REG_EMAIL}\",\"password\":\"Password1!\",\"password_confirmation\":\"Password1!\",\"phone\":\"+10000000999\"}")
record "POST /api/auth/register" "$(http_code_from_r "$R")"

R=$(req POST "/api/auth/login" '{"email":"admin@loomsolutions.com","password":"admin123"}')
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "POST /api/auth/login" "$HTTP"
STAFF_TOKEN=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null || true)

if [[ -z "${STAFF_TOKEN:-}" ]]; then
  echo "FATAL: no staff token from login. Seed DB (admin@loomsolutions.com / admin123)."
  exit 1
fi

R=$(req GET "/api/auth/me" "" "$STAFF_TOKEN")
record "GET /api/auth/me" "$(http_code_from_r "$R")"

# --- DASHBOARD ---
R=$(req GET "/api/dashboard" "" "$STAFF_TOKEN")
record "GET /api/dashboard" "$(http_code_from_r "$R")"

# --- PROPERTIES ---
R=$(req GET "/api/properties" "" "$STAFF_TOKEN")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "GET /api/properties" "$HTTP"
PROP_ID=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); items=d.get('data',{}).get('items') or []; print(items[0]['id'] if items else '')" 2>/dev/null || true)

R=$(req POST "/api/properties" '{"name":"Smoke Property","type":"Residential","address":"Test St","city":"Addis Ababa","country":"Ethiopia","total_units":1}' "$STAFF_TOKEN")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "POST /api/properties" "$HTTP"
NEW_PROP_ID=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('property',{}).get('id',''))" 2>/dev/null || true)
[[ -n "$NEW_PROP_ID" ]] || NEW_PROP_ID="$PROP_ID"

# --- TENANTS ---
R=$(req GET "/api/tenants" "" "$STAFF_TOKEN")
record "GET /api/tenants" "$(http_code_from_r "$R")"

TENANT_EMAIL="tenant_smoke_$(date +%s)@example.com"
R=$(req POST "/api/tenants" "{\"name\":\"Smoke Tenant\",\"email\":\"${TENANT_EMAIL}\",\"phone\":\"+251911199999\",\"id_number\":\"ID-SMK-1\"}" "$STAFF_TOKEN")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "POST /api/tenants" "$HTTP"
TENANT_ID=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('tenant',{}).get('id',''))" 2>/dev/null || true)

# --- UNITS ---
R=$(req GET "/api/units" "" "$STAFF_TOKEN")
record "GET /api/units" "$(http_code_from_r "$R")"

UNUM="SMK-$(date +%s)"
R=$(req POST "/api/units" "{\"property_id\":${NEW_PROP_ID},\"unit_number\":\"${UNUM}\",\"type\":\"Standard\",\"floor\":\"1\",\"size_sqm\":50,\"rent_amount\":15000,\"status\":\"available\"}" "$STAFF_TOKEN")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "POST /api/units" "$HTTP"
UNIT_ID=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('unit',{}).get('id',''))" 2>/dev/null || true)

# --- LEASES ---
R=$(req GET "/api/leases" "" "$STAFF_TOKEN")
record "GET /api/leases" "$(http_code_from_r "$R")"

if [[ -n "${TENANT_ID:-}" && -n "${UNIT_ID:-}" ]]; then
  R=$(req POST "/api/leases" "{\"tenant_id\":${TENANT_ID},\"unit_id\":${UNIT_ID},\"start_date\":\"2026-01-01\",\"end_date\":\"2027-01-01\",\"rent_amount\":15000,\"deposit_amount\":5000,\"status\":\"active\"}" "$STAFF_TOKEN")
  record "POST /api/leases" "$(http_code_from_r "$R")"
else
  TOTAL=$((TOTAL + 1))
  FAIL=$((FAIL + 1))
  echo "[FAIL] POST /api/leases → skipped (missing tenant/unit id)"
fi

# --- INVOICES ---
R=$(req GET "/api/invoices" "" "$STAFF_TOKEN")
record "GET /api/invoices" "$(http_code_from_r "$R")"
R=$(req GET "/api/invoices?status=pending" "" "$STAFF_TOKEN")
record "GET /api/invoices?status=pending" "$(http_code_from_r "$R")"
R=$(req GET "/api/invoices?status=overdue" "" "$STAFF_TOKEN")
record "GET /api/invoices?status=overdue" "$(http_code_from_r "$R")"
R=$(req GET "/api/invoices?status=paid" "" "$STAFF_TOKEN")
record "GET /api/invoices?status=paid" "$(http_code_from_r "$R")"
R=$(req POST "/api/invoices/generate-monthly" '{}' "$STAFF_TOKEN")
record "POST /api/invoices/generate-monthly" "$(http_code_from_r "$R")"

# --- PAYMENTS ---
R=$(req GET "/api/payments" "" "$STAFF_TOKEN")
record "GET /api/payments" "$(http_code_from_r "$R")"

# Invoice id + tenant id must come from the same row (do not mix first payment with first invoice).
R=$(req GET "/api/invoices?status=pending" "" "$STAFF_TOKEN")
PAY_SRC=$(json_body_from_r "$R")
INV_ID=$(echo "$PAY_SRC" | python3 -c "import sys,json; d=json.load(sys.stdin); items=d.get('data',{}).get('items') or []; print(items[0]['id'] if items else '')" 2>/dev/null || true)
TENANT_FOR_PAY=$(echo "$PAY_SRC" | python3 -c "import sys,json; d=json.load(sys.stdin); items=d.get('data',{}).get('items') or []; print(items[0].get('tenant_id','') if items else '')" 2>/dev/null || true)
if [[ -z "$INV_ID" ]]; then
  R=$(req GET "/api/invoices" "" "$STAFF_TOKEN")
  PAY_SRC=$(json_body_from_r "$R")
  INV_ID=$(echo "$PAY_SRC" | python3 -c "import sys,json; d=json.load(sys.stdin); items=d.get('data',{}).get('items') or []; print(items[0]['id'] if items else '')" 2>/dev/null || true)
  TENANT_FOR_PAY=$(echo "$PAY_SRC" | python3 -c "import sys,json; d=json.load(sys.stdin); items=d.get('data',{}).get('items') or []; print(items[0].get('tenant_id','') if items else '')" 2>/dev/null || true)
fi

if [[ -n "${INV_ID:-}" && -n "${TENANT_FOR_PAY:-}" ]]; then
  REF="SMK-$(date +%s)"
  R=$(req POST "/api/payments" "{\"invoice_id\":${INV_ID},\"tenant_id\":${TENANT_FOR_PAY},\"amount\":100,\"method\":\"cash\",\"reference\":\"${REF}\"}" "$STAFF_TOKEN")
  record "POST /api/payments" "$(http_code_from_r "$R")"
else
  TOTAL=$((TOTAL + 1))
  FAIL=$((FAIL + 1))
  echo "[FAIL] POST /api/payments → skipped (no invoice/tenant from seed)"
fi

# --- MAINTENANCE ---
R=$(req GET "/api/maintenance" "" "$STAFF_TOKEN")
record "GET /api/maintenance" "$(http_code_from_r "$R")"
MPROP="${NEW_PROP_ID:-$PROP_ID}"
if [[ -n "$MPROP" ]]; then
  R=$(req POST "/api/maintenance" "{\"property_id\":${MPROP},\"unit\":\"A-1\",\"title\":\"Smoke leak\",\"description\":\"Test\",\"priority\":\"medium\"}" "$STAFF_TOKEN")
  record "POST /api/maintenance" "$(http_code_from_r "$R")"
else
  TOTAL=$((TOTAL + 1))
  FAIL=$((FAIL + 1))
  echo "[FAIL] POST /api/maintenance → skipped (no property id)"
fi

# --- MARKETPLACE ---
R=$(req GET "/api/marketplace/categories" "" "$STAFF_TOKEN")
record "GET /api/marketplace/categories" "$(http_code_from_r "$R")"
R=$(req GET "/api/marketplace/vendors" "" "$STAFF_TOKEN")
record "GET /api/marketplace/vendors" "$(http_code_from_r "$R")"
R=$(req GET "/api/marketplace/products" "" "$STAFF_TOKEN")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "GET /api/marketplace/products" "$HTTP"
PROD_ID=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); p=d.get('data',{}).get('products') or []; print(p[0]['id'] if p else '')" 2>/dev/null || true)

if [[ -n "${PROD_ID:-}" && -n "${PROP_ID:-}" ]]; then
  R=$(req POST "/api/marketplace/orders" "{\"property_id\":${PROP_ID},\"product_id\":${PROD_ID},\"quantity\":1}" "$STAFF_TOKEN")
  record "POST /api/marketplace/orders" "$(http_code_from_r "$R")"
else
  TOTAL=$((TOTAL + 1))
  FAIL=$((FAIL + 1))
  echo "[FAIL] POST /api/marketplace/orders → skipped (product/property)"
fi
R=$(req GET "/api/marketplace/orders" "" "$STAFF_TOKEN")
record "GET /api/marketplace/orders" "$(http_code_from_r "$R")"

# --- PORTAL ---
R=$(req POST "/api/portal/request-otp" '{"phone":"+251911100001"}')
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
record "POST /api/portal/request-otp" "$HTTP"
OTP=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('debug_code',''))" 2>/dev/null || true)

if [[ -n "$OTP" ]]; then
  R=$(req POST "/api/portal/verify-otp" "{\"phone\":\"+251911100001\",\"code\":\"${OTP}\"}")
  HTTP=$(http_code_from_r "$R")
  BODY=$(json_body_from_r "$R")
  record "POST /api/portal/verify-otp" "$HTTP"
  PORTAL_TOKEN=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null || true)
else
  TOTAL=$((TOTAL + 1))
  FAIL=$((FAIL + 1))
  echo "[FAIL] POST /api/portal/verify-otp → skipped (set APP_DEBUG=true for debug_code)"
  PORTAL_TOKEN=""
fi

if [[ -n "${PORTAL_TOKEN:-}" ]]; then
  R=$(req GET "/api/portal/me" "" "$PORTAL_TOKEN")
  record "GET /api/portal/me" "$(http_code_from_r "$R")"
  R=$(req GET "/api/portal/invoices" "" "$PORTAL_TOKEN")
  record "GET /api/portal/invoices" "$(http_code_from_r "$R")"
  R=$(req GET "/api/portal/payments" "" "$PORTAL_TOKEN")
  record "GET /api/portal/payments" "$(http_code_from_r "$R")"
  R=$(req GET "/api/portal/maintenance" "" "$PORTAL_TOKEN")
  record "GET /api/portal/maintenance" "$(http_code_from_r "$R")"
  R=$(req GET "/api/portal/lease" "" "$PORTAL_TOKEN")
  record "GET /api/portal/lease" "$(http_code_from_r "$R")"
else
  for _l in "GET /api/portal/me" "GET /api/portal/invoices" "GET /api/portal/payments" "GET /api/portal/maintenance" "GET /api/portal/lease"; do
    TOTAL=$((TOTAL + 1))
    FAIL=$((FAIL + 1))
    echo "[FAIL] ${_l} → skipped (no portal token)"
  done
fi

# --- NOTIFICATIONS ---
R=$(req GET "/api/notifications" "" "$STAFF_TOKEN")
record "GET /api/notifications" "$(http_code_from_r "$R")"
R=$(req GET "/api/notifications/count" "" "$STAFF_TOKEN")
record "GET /api/notifications/count" "$(http_code_from_r "$R")"

# --- SMS / WHATSAPP LOGS ---
R=$(req GET "/api/sms-logs" "" "$STAFF_TOKEN")
record "GET /api/sms-logs" "$(http_code_from_r "$R")"
R=$(req GET "/api/whatsapp-logs" "" "$STAFF_TOKEN")
record "GET /api/whatsapp-logs" "$(http_code_from_r "$R")"

# --- REPORTS ---
R=$(req GET "/api/reports/occupancy" "" "$STAFF_TOKEN")
record "GET /api/reports/occupancy" "$(http_code_from_r "$R")"
R=$(req GET "/api/reports/revenue" "" "$STAFF_TOKEN")
record "GET /api/reports/revenue" "$(http_code_from_r "$R")"
R=$(req GET "/api/reports/overdue" "" "$STAFF_TOKEN")
record "GET /api/reports/overdue" "$(http_code_from_r "$R")"
R=$(req GET "/api/reports/maintenance" "" "$STAFF_TOKEN")
record "GET /api/reports/maintenance" "$(http_code_from_r "$R")"
R=$(req GET "/api/reports/leases" "" "$STAFF_TOKEN")
record "GET /api/reports/leases" "$(http_code_from_r "$R")"
R=$(req GET "/api/reports/sms" "" "$STAFF_TOKEN")
record "GET /api/reports/sms" "$(http_code_from_r "$R")"

# --- SETTINGS ---
R=$(req GET "/api/settings" "" "$STAFF_TOKEN")
record "GET /api/settings" "$(http_code_from_r "$R")"
R=$(req PUT "/api/settings" '{"name":"Loom Solutions PLC"}' "$STAFF_TOKEN")
record "PUT /api/settings" "$(http_code_from_r "$R")"

# --- SUPER ADMIN (Bearer from cache; login first — not counted in the 47 checklist) ---
R=$(req POST "/api/super-admin/login" "{\"email\":\"${SUPER_ADMIN_EMAIL}\",\"password\":\"${SUPER_ADMIN_PASSWORD}\"}")
HTTP=$(http_code_from_r "$R")
BODY=$(json_body_from_r "$R")
if [[ ! "$HTTP" =~ ^2[0-9][0-9]$ ]]; then
  echo "WARN: POST /api/super-admin/login → HTTP ${HTTP} (set SUPER_ADMIN_* in .env to match .env.example)"
fi
SA_TOKEN=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null || true)

if [[ -z "${SA_TOKEN:-}" ]]; then
  echo "WARN: Super admin token missing — check SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD in .env"
  for _l in "GET /api/super-admin/dashboard" "GET /api/super-admin/companies" "GET /api/super-admin/marketplace/vendors" "GET /api/super-admin/marketplace/products" "GET /api/super-admin/marketplace/orders" "GET /api/super-admin/marketplace/categories"; do
    TOTAL=$((TOTAL + 1))
    FAIL=$((FAIL + 1))
    echo "[FAIL] ${_l} → skipped (super admin login failed)"
  done
else
  R=$(req GET "/api/super-admin/dashboard" "" "$SA_TOKEN")
  record "GET /api/super-admin/dashboard" "$(http_code_from_r "$R")"
  R=$(req GET "/api/super-admin/companies" "" "$SA_TOKEN")
  record "GET /api/super-admin/companies" "$(http_code_from_r "$R")"
  R=$(req GET "/api/super-admin/marketplace/vendors" "" "$SA_TOKEN")
  record "GET /api/super-admin/marketplace/vendors" "$(http_code_from_r "$R")"
  R=$(req GET "/api/super-admin/marketplace/products" "" "$SA_TOKEN")
  record "GET /api/super-admin/marketplace/products" "$(http_code_from_r "$R")"
  R=$(req GET "/api/super-admin/marketplace/orders" "" "$SA_TOKEN")
  record "GET /api/super-admin/marketplace/orders" "$(http_code_from_r "$R")"
  R=$(req GET "/api/super-admin/marketplace/categories" "" "$SA_TOKEN")
  record "GET /api/super-admin/marketplace/categories" "$(http_code_from_r "$R")"
fi

echo ""
echo "================ SUMMARY ================"
echo "${PASS} out of ${TOTAL} endpoints returned success (HTTP 2xx)."
echo "Failed or skipped: $FAIL"
echo "========================================="

if [[ "$FAIL" -gt 0 ]]; then
  exit 1
fi
