#!/usr/bin/env bash
# ============================================================================
# SecuAI Phase 1 Smoke Test
# ============================================================================
# Runs every API endpoint and verifies expected responses.
# Run from the VPS or your laptop. Requires curl + jq.
#
#   chmod +x smoke-test.sh
#   ./smoke-test.sh https://security.astrionix.io
#
# Exits 0 on full pass, 1 if anything fails.
# ============================================================================
set -e

BASE_URL="${1:-https://security.astrionix.io}"
TIMESTAMP=$(date +%s)
EMAIL="smoketest-${TIMESTAMP}@astrionix.io"
PASSWORD="SmokeTest$(date +%s)!Aa"

PASS=0
FAIL=0

green() { printf "\033[32m%s\033[0m\n" "$1"; }
red()   { printf "\033[31m%s\033[0m\n" "$1"; }
blue()  { printf "\033[34m%s\033[0m\n" "$1"; }

assert_eq() {
    local label="$1"
    local actual="$2"
    local expected="$3"
    if [[ "$actual" == "$expected" ]]; then
        green "  ✓ $label"
        ((PASS++))
    else
        red "  ✗ $label  (expected '$expected', got '$actual')"
        ((FAIL++))
    fi
}

assert_contains() {
    local label="$1"
    local haystack="$2"
    local needle="$3"
    if echo "$haystack" | grep -q "$needle"; then
        green "  ✓ $label"
        ((PASS++))
    else
        red "  ✗ $label  (response did not contain '$needle')"
        red "    Response: $haystack"
        ((FAIL++))
    fi
}

if ! command -v jq >/dev/null 2>&1; then
    red "jq is required. Install: apt-get install -y jq"
    exit 1
fi

blue "=== SecuAI Phase 1 Smoke Test ==="
echo "Target: $BASE_URL"
echo "Test email: $EMAIL"
echo ""

# 1. Health check
blue "[1/8] GET /api/health"
HEALTH=$(curl -sf "$BASE_URL/api/health")
assert_contains "responds with ok:true" "$HEALTH" '"ok":true'
assert_contains "reports phase-1 version" "$HEALTH" 'phase-1'

# 2. Signup
blue "[2/8] POST /api/auth/signup"
SIGNUP=$(curl -sf -X POST "$BASE_URL/api/auth/signup" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\",\"name\":\"Smoke Test\"}")
TOKEN=$(echo "$SIGNUP" | jq -r '.token // empty')
USER_ID=$(echo "$SIGNUP" | jq -r '.user.id // empty')

if [[ -n "$TOKEN" ]]; then
    green "  ✓ signup returned a token"
    ((PASS++))
else
    red "  ✗ signup did not return a token"
    red "    Response: $SIGNUP"
    ((FAIL++))
    exit 1
fi
assert_contains "returns user with email" "$SIGNUP" "\"email\":\"$EMAIL\""

# 3. Duplicate signup is rejected
blue "[3/8] POST /api/auth/signup (duplicate email)"
DUP=$(curl -s -X POST "$BASE_URL/api/auth/signup" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\"}")
assert_contains "rejects duplicate email" "$DUP" 'already been taken'

# 4. Login
blue "[4/8] POST /api/auth/login"
LOGIN=$(curl -sf -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
LOGIN_TOKEN=$(echo "$LOGIN" | jq -r '.token // empty')
if [[ -n "$LOGIN_TOKEN" ]]; then
    green "  ✓ login returned a token"
    ((PASS++))
    TOKEN="$LOGIN_TOKEN"
else
    red "  ✗ login failed"
    red "    Response: $LOGIN"
    ((FAIL++))
fi

# 5. Wrong password is rejected
blue "[5/8] POST /api/auth/login (wrong password)"
BADLOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"WrongPassword123!\"}")
assert_contains "rejects wrong password" "$BADLOGIN" 'invalid_credentials'

# 6. /api/me returns the user with empty tenants
blue "[6/8] GET /api/me"
ME=$(curl -sf "$BASE_URL/api/me" -H "Authorization: Bearer $TOKEN")
assert_contains "returns user object" "$ME" "\"id\":\"$USER_ID\""
assert_contains "returns tenants array" "$ME" '"tenants":\[\]'

# 7. Create a workspace
blue "[7/8] POST /api/tenants"
TENANT=$(curl -sf -X POST "$BASE_URL/api/tenants" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"name":"Smoke Test Workspace"}')
TENANT_ID=$(echo "$TENANT" | jq -r '.tenant.id // empty')
if [[ -n "$TENANT_ID" ]]; then
    green "  ✓ workspace created (id: $TENANT_ID)"
    ((PASS++))
else
    red "  ✗ workspace creation failed"
    red "    Response: $TENANT"
    ((FAIL++))
fi
assert_contains "user is admin of new workspace" "$TENANT" '"role":"admin"'

# 8. /api/me now shows the workspace
blue "[8/8] GET /api/me (after workspace creation)"
ME2=$(curl -sf "$BASE_URL/api/me" -H "Authorization: Bearer $TOKEN")
assert_contains "tenants array now has the workspace" "$ME2" "\"id\":\"$TENANT_ID\""
assert_contains "user has admin role" "$ME2" '"role":"admin"'

# Summary
echo ""
blue "=== Results ==="
green "PASSED: $PASS"
if [[ $FAIL -gt 0 ]]; then
    red "FAILED: $FAIL"
    exit 1
else
    echo "FAILED: 0"
    green "✓ All Phase 1 endpoints working."
fi
