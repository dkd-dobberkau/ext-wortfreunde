#!/usr/bin/env bash
#
# Send a signed test webhook to a Wortfreunde Connector endpoint.
#
# Usage:
#   ./send-webhook.sh <payload-file> [url] [secret]
#
# Examples:
#   ./send-webhook.sh payloads/ping.json
#   ./send-webhook.sh payloads/post.published.json https://main.wortfreunde.202.dkd.dev/wortfreunde/webhook
#   ./send-webhook.sh payloads/post.published.json https://example.com/wortfreunde/webhook my-secret-key
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PAYLOAD_FILE="${1:?Usage: $0 <payload-file> [url] [secret]}"
URL="${2:-https://main.wortfreunde.202.dkd.dev/wortfreunde/webhook}"
SECRET="${3:-db7cfada3cd206e9bf5109431e5e3f34fc37a09a4d07889ec51be67f8859d0f4}"

# Resolve relative paths from current working directory
if [[ ! "$PAYLOAD_FILE" = /* ]]; then
    PAYLOAD_FILE="$(pwd)/${PAYLOAD_FILE}"
fi

if [[ ! -f "$PAYLOAD_FILE" ]]; then
    echo "ERROR: Payload file not found: $PAYLOAD_FILE" >&2
    exit 1
fi

# Read payload (no trailing newline)
BODY=$(cat "$PAYLOAD_FILE" | tr -d '\n')

# Extract event from JSON
EVENT=$(echo "$BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('event','unknown'))" 2>/dev/null || echo "unknown")

# Generate signature
TIMESTAMP=$(date +%s)
DELIVERY="test-${TIMESTAMP}-$$"
SIGNATURE=$(printf '%s.%s' "$TIMESTAMP" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/.*= //')

echo "=== Wortfreunde Webhook Test ==="
echo "Event:    $EVENT"
echo "Payload:  $(basename "$PAYLOAD_FILE")"
echo "URL:      $URL"
echo "Delivery: $DELIVERY"
echo ""

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    -H "X-Wortfreunde-Event: ${EVENT}" \
    -H "X-Wortfreunde-Delivery: ${DELIVERY}" \
    -H "X-Wortfreunde-Signature: sha256=${SIGNATURE}" \
    -H "X-Wortfreunde-Timestamp: ${TIMESTAMP}" \
    -d "$BODY" \
    "$URL")

HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY_RESPONSE=$(echo "$RESPONSE" | sed '$d')

if [[ "$HTTP_CODE" -ge 200 && "$HTTP_CODE" -lt 300 ]]; then
    echo "OK ($HTTP_CODE)"
else
    echo "FAILED ($HTTP_CODE)"
fi
echo "$BODY_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$BODY_RESPONSE"
echo ""
