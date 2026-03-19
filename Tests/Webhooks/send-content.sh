#!/usr/bin/env bash
#
# Send markdown files as post.published webhooks to test content rendering.
#
# Usage:
#   ./send-content.sh content/01-simple.md           # Single file
#   ./send-content.sh content/                        # All files in directory
#   ./send-content.sh content/ https://example.com/wortfreunde/webhook my-secret
#
set -euo pipefail

TARGET="${1:?Usage: $0 <markdown-file-or-dir> [url] [secret]}"
URL="${2:-https://main.wortfreunde.202.dkd.dev/wortfreunde/webhook}"
SECRET="${3:-db7cfada3cd206e9bf5109431e5e3f34fc37a09a4d07889ec51be67f8859d0f4}"

# Resolve relative paths
if [[ ! "$TARGET" = /* ]]; then
    TARGET="$(pwd)/${TARGET}"
fi

# Collect markdown files
if [[ -d "$TARGET" ]]; then
    FILES=("$TARGET"/*.md)
else
    FILES=("$TARGET")
fi

POST_ID=2000

for FILE in "${FILES[@]}"; do
    if [[ ! -f "$FILE" ]]; then
        echo "SKIP: $FILE not found"
        continue
    fi

    BASENAME=$(basename "$FILE" .md)
    MARKDOWN=$(cat "$FILE")

    # Extract title from first H1 or filename
    TITLE=$(echo "$MARKDOWN" | grep -m1 '^# ' | sed 's/^# //' || echo "$BASENAME")
    if [[ -z "$TITLE" ]]; then
        TITLE="$BASENAME"
    fi

    # Build JSON payload using python for safe escaping
    BODY=$(python3 -c "
import json, sys

markdown = sys.stdin.read()
title = '''$TITLE'''
post_id = $POST_ID

payload = {
    'event': 'post.published',
    'timestamp': '2026-03-19T18:00:00Z',
    'account': {'id': 89, 'name': 'dkd_de'},
    'data': {
        'post': {
            'id': post_id,
            'title': title,
            'body': markdown,
            'teaser': None,
            'slug': title.lower().replace(' ', '-')[:50],
            'publication_status': 'published',
            'published_at': '2026-03-19T18:00:00Z',
            'created_at': '2026-03-19T17:00:00Z',
            'updated_at': '2026-03-19T18:00:00Z',
            'meta_title': None,
            'meta_description': None,
            'tags': [],
            'media': [],
            'channel': {'id': 161, 'title': 'Blog', 'platform': 'git'}
        }
    }
}
print(json.dumps(payload, ensure_ascii=False), end='')
" <<< "$MARKDOWN")

    # Sign
    TIMESTAMP=$(date +%s)
    DELIVERY="content-test-${POST_ID}-${TIMESTAMP}"
    SIGNATURE=$(printf '%s.%s' "$TIMESTAMP" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/.*= //')

    echo "=== $BASENAME (post_id=$POST_ID) ==="

    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -H "X-Wortfreunde-Event: post.published" \
        -H "X-Wortfreunde-Delivery: ${DELIVERY}" \
        -H "X-Wortfreunde-Signature: sha256=${SIGNATURE}" \
        -H "X-Wortfreunde-Timestamp: ${TIMESTAMP}" \
        -d "$BODY" \
        "$URL")

    HTTP_CODE=$(echo "$RESPONSE" | tail -1)
    BODY_RESPONSE=$(echo "$RESPONSE" | sed '$d')

    if [[ "$HTTP_CODE" -ge 200 && "$HTTP_CODE" -lt 300 ]]; then
        echo "OK ($HTTP_CODE) — $TITLE"
    else
        echo "FAILED ($HTTP_CODE) — $TITLE"
        echo "$BODY_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$BODY_RESPONSE"
    fi
    echo ""

    POST_ID=$((POST_ID + 1))
done
