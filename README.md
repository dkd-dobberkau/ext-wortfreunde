# Wortfreunde Connector for TYPO3

TYPO3 extension that receives blog posts from [wortfreunde.ch](https://wortfreunde.ch) via webhooks and converts them into `tt_content` elements.

## Requirements

- TYPO3 v13.4 LTS
- PHP 8.3+
- Composer

## Installation

```bash
composer require wortfreunde/wortfreunde-connector
```

Then run **Analyze Database Structure** to create the webhook log table.

## Configuration

Settings are managed in the TYPO3 backend under **System → Wortfreunde → Settings**. They are stored in the database (`sys_registry`), so they work on read-only filesystems (Docker).

| Setting | Description | Default |
|---------|-------------|---------|
| Webhook Secret | Shared HMAC-SHA256 secret for signature verification | *(empty = no verification)* |
| Allowed Channel IDs | Comma-separated channel IDs to process (empty = all) | *(empty)* |
| Default Page UID | Target page UID for new `tt_content` elements | `0` |
| Default Language UID | `sys_language_uid` for created content | `0` |
| Default ColPos | Column position (`colPos`) | `0` |
| Content Type | CType: `text` or `textmedia` | `text` |
| Enable Logging | Log all incoming webhook requests | `true` |

## Webhook Endpoint

After installation, the webhook is available at:

```
POST https://your-typo3-site.example/wortfreunde/webhook
```

Configure this URL in **Wortfreunde Studio → Settings → Webhooks**.

## How It Works

1. You create or edit a post in Wortfreunde Studio
2. Wortfreunde sends a signed HTTP request to your TYPO3 webhook endpoint
3. The extension verifies the signature, checks the channel filter, and processes the event
4. A `tt_content` element is created or updated on the configured target page

Webhooks are global per Wortfreunde account — all channels send to the same endpoint. Use **Allowed Channel IDs** to filter which channels are processed.

## Events

| Event | Behavior |
|-------|----------|
| `post.published` | Creates new `tt_content` element, or updates if post ID already exists |
| `post.updated` | Updates existing content matched by post ID, falls back to create |
| `post.publishing_pending` | Creates/updates content as **hidden** (awaiting publish confirmation) |
| `post.unpublished` | Hides existing content element |
| `ping` | Returns pong response (connection test) |

## Field Mapping

| Wortfreunde Field | TYPO3 tt_content Field |
|-------------------|------------------------|
| `data.post.title` | `header` |
| `data.post.body` | `bodytext` (converted from Markdown to HTML) |
| `data.post.teaser` | `subheader` |
| `data.post.published_at` | `date` |
| `data.post.id` | Used for deduplication via webhook log |

## Webhook Headers

Wortfreunde sends these headers with each delivery:

| Header | Description |
|--------|-------------|
| `X-Wortfreunde-Signature` | HMAC-SHA256 signature (with `sha256=` prefix) |
| `X-Wortfreunde-Timestamp` | Unix timestamp of signature creation |
| `X-Wortfreunde-Event` | Event type |
| `X-Wortfreunde-Delivery` | Unique delivery identifier |

## Signature Verification

If a webhook secret is configured, the extension verifies the `X-Wortfreunde-Signature` header:

1. Strip `sha256=` prefix from signature
2. Concatenate `{timestamp}.{body}` using the `X-Wortfreunde-Timestamp` header
3. Compute HMAC-SHA256 with the shared secret
4. Compare using timing-safe comparison

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

A **Wortfreunde** module appears under **System** in the TYPO3 backend with two tabs:

- **Webhook Log** — Event history with status, timestamps, and linked `tt_content` UIDs. Statistics overview (total / processed / pending / error).
- **Settings** — Configure webhook secret, channel filter, content defaults, and logging.

## Architecture

```
wortfreunde_connector/
├── Classes/
│   ├── Controller/
│   │   └── WebhookLogController.php    # Backend module (log + settings)
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
