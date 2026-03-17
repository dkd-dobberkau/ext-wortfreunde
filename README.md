# Wortfreunde Connector for TYPO3

TYPO3 extension that receives blog posts from [wortfreunde.ch](https://wortfreunde.ch) via webhooks and converts them into `tt_content` elements.

## Requirements

- TYPO3 v12.4 LTS or v13.4 LTS
- PHP 8.1+
- Composer

## Installation

```bash
composer require wortfreunde/wortfreunde-connector
```

Then activate the extension via TYPO3 Admin Tools → Extensions, and run **Analyze Database Structure** to create the webhook log table.

## Configuration

In **Admin Tools → Settings → Extension Configuration → wortfreunde_connector**:

| Setting | Description | Default |
|---------|-------------|---------|
| `webhook.secret` | Shared HMAC-SHA256 secret for signature verification | *(empty = no verification)* |
| `webhook.defaultPageUid` | Target page UID for new `tt_content` elements | `0` |
| `webhook.defaultLanguageUid` | `sys_language_uid` for created content | `0` |
| `webhook.defaultColPos` | Column position (`colPos`) | `0` |
| `webhook.defaultContentType` | CType: `text` or `textmedia` | `text` |
| `webhook.enableLogging` | Log all incoming webhook requests | `true` |

## Webhook Endpoint

After installation, the webhook is available at:

```
POST https://your-typo3-site.example/wortfreunde/webhook
```

### Payload Format

The extension expects the webhook payload format as documented at [wortfreunde.ch/docs/api-reference/webhooks](https://wortfreunde.ch/docs/api-reference/webhooks):

```json
{
  "event": "post.published",
  "timestamp": "2026-03-09T10:00:00Z",
  "account": {
    "id": 42,
    "name": "Team Name"
  },
  "data": {
    "post": {
      "id": 214,
      "title": "My Blog Post Title",
      "body": "# Heading\n\nMarkdown content with **bold** text.",
      "teaser": "Short summary of the post",
      "slug": "my-blog-post-title",
      "publication_status": "published",
      "published_at": "2026-03-09T10:00:00Z",
      "tags": [{"id": 1, "name": "tech"}],
      "media": [],
      "channel": {"id": 161, "title": "Blog", "platform": "git"}
    }
  }
}
```

### Events

| Event | Behavior |
|-------|----------|
| `post.published` | Creates new `tt_content` element, or updates if post ID already exists |
| `post.updated` | Updates existing content matched by post ID, falls back to create |

### Field Mapping

| Wortfreunde Field | TYPO3 tt_content Field |
|-------------------|------------------------|
| `data.post.title` | `header` |
| `data.post.body` | `bodytext` (converted from Markdown to HTML) |
| `data.post.teaser` | `subheader` |
| `data.post.published_at` | `date` |
| `data.post.id` | Used for deduplication via webhook log |

### Webhook Headers

Wortfreunde sends these headers with each delivery:

| Header | Description |
|--------|-------------|
| `X-Wortfreunde-Signature` | HMAC-SHA256 signature for verification |
| `X-Wortfreunde-Timestamp` | Unix timestamp of signature creation |
| `X-Wortfreunde-Event` | Event type (`post.published`, `post.updated`) |
| `X-Wortfreunde-Delivery` | Unique delivery identifier |

### Signature Verification

If `webhook.secret` is configured, the extension verifies the `X-Wortfreunde-Signature` header. The signature is computed as HMAC-SHA256 over `{timestamp}.{body}`, where timestamp comes from the `X-Wortfreunde-Timestamp` header.

### Response Examples

**Success (201):**
```json
{
  "success": true,
  "message": "Webhook processed successfully.",
  "data": {
    "action": "created",
    "tt_content_uid": 123,
    "page_uid": 42,
    "post_id": 214,
    "webhook_id": "214"
  }
}
```

**Validation Error (422):**
```json
{
  "error": "Missing \"data.post\" in webhook payload."
}
```

## Markdown Support

Powered by [league/commonmark](https://commonmark.thephpleague.com/) with GitHub-flavored extensions:

- Headings, paragraphs, bold, italic
- Tables
- Autolinks
- Strikethrough (`~~text~~`)
- Task lists
- Code blocks (fenced and indented)
- Blockquotes
- Images and links

Unsafe HTML input (`<script>`, etc.) is automatically stripped.

## Backend Module

A **Wortfreunde** module appears under *Web* in the TYPO3 backend, showing:

- Webhook log with event type, status, timestamps, and linked `tt_content` UIDs
- Statistics overview (total / processed / pending / error)

## Architecture

```
wortfreunde_connector/
├── Classes/
│   ├── Controller/
│   │   └── WebhookLogController.php    # Backend module
│   ├── Middleware/
│   │   └── WebhookMiddleware.php       # PSR-15 webhook endpoint
│   └── Service/
│       ├── MarkdownConverterService.php # Markdown → HTML
│       └── WebhookProcessorService.php  # Payload → tt_content
├── Configuration/
│   ├── Backend/Modules.php
│   ├── Icons.php
│   ├── RequestMiddlewares.php
│   ├── Services.yaml
│   └── TCA/
├── Resources/
│   ├── Private/Language/
│   └── Private/Templates/Backend/
└── Tests/Unit/
```

## License

GPL-2.0-or-later
