<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;

/**
 * Processes incoming webhook payloads from the Wortfreunde API.
 *
 * Expected payload format (as documented at wortfreunde.ch/docs/api-reference/webhooks):
 * {
 *   "event": "post.published",
 *   "timestamp": "2026-03-09T10:00:00Z",
 *   "account": { "id": 42, "name": "Team Name" },
 *   "data": {
 *     "post": {
 *       "id": 214,
 *       "title": "Blog Post Title",
 *       "body": "# Markdown content...",
 *       "teaser": "Short summary",
 *       "slug": "blog-post-title",
 *       "publication_status": "published",
 *       "published_at": "2026-03-09T10:00:00Z",
 *       "created_at": "2026-03-08T10:00:00Z",
 *       "updated_at": "2026-03-09T10:00:00Z",
 *       "meta_title": null,
 *       "meta_description": null,
 *       "tags": [{ "id": 1, "name": "tech" }],
 *       "media": [],
 *       "channel": { "id": 161, "title": "Blog", "platform": "git" }
 *     }
 *   }
 * }
 *
 * Events: post.published (create or update), post.updated (update)
 */
class WebhookProcessorService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly MarkdownConverterService $markdownConverter,
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Registry $registry,
    ) {}

    /**
     * Process a webhook payload and return result info.
     *
     * @param array $payload The decoded JSON payload from Wortfreunde
     * @param string $event The event type from X-Wortfreunde-Event header
     * @param string $deliveryId Unique delivery ID from X-Wortfreunde-Delivery header
     * @return array Result with tt_content_uid, page_uid, action
     * @throws \InvalidArgumentException on validation errors
     */
    public function process(array $payload, string $event = '', string $deliveryId = ''): array
    {
        $config = $this->getConfiguration();

        // Use header event or fall back to payload event
        $event = $event ?: ($payload['event'] ?? '');
        if (empty($event)) {
            throw new \InvalidArgumentException('Missing event type. Provide X-Wortfreunde-Event header or "event" field.');
        }

        // Respond to ping events
        if ($event === 'ping') {
            if ((bool)($config['enableLogging'] ?? true)) {
                $this->logWebhook($deliveryId ?: uniqid('ping_', true), $payload, 'processed', $event, $deliveryId);
            }
            return [
                'action' => 'pong',
                'message' => 'Webhook connection verified.',
            ];
        }

        $post = $payload['data']['post'] ?? null;
        if ($post === null) {
            throw new \InvalidArgumentException('Missing "data.post" in webhook payload.');
        }

        $postId = (string)($post['id'] ?? '');
        $webhookId = !empty($postId) ? $postId : ($deliveryId ?: uniqid('wf_', true));

        if ((bool)($config['enableLogging'] ?? true)) {
            $this->logWebhook($webhookId, $payload, 'pending', $event, $deliveryId);
        }

        try {
            $result = match ($event) {
                'post.published' => $this->handlePublished($post, $config, $webhookId),
                'post.updated' => $this->handleUpdated($post, $config, $webhookId),
                default => throw new \InvalidArgumentException("Unsupported event: \"{$event}\". Supported: post.published, post.updated."),
            };

            if ((bool)($config['enableLogging'] ?? true)) {
                $this->updateWebhookLog($webhookId, 'processed', '', $result['tt_content_uid'] ?? 0);
            }

            return $result;
        } catch (\Throwable $e) {
            if ((bool)($config['enableLogging'] ?? true)) {
                $this->updateWebhookLog($webhookId, 'error', $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Handle post.published: create new content or update if already exists.
     */
    private function handlePublished(array $post, array $config, string $webhookId): array
    {
        $postId = (string)($post['id'] ?? '');

        // Check for existing content to decide create vs update
        if (!empty($postId)) {
            $existing = $this->findContentByWebhookId($postId);
            if ($existing) {
                return $this->updateContent($existing, $post, $config, $webhookId);
            }
        }

        return $this->createContent($post, $config, $webhookId);
    }

    /**
     * Handle post.updated: update existing content or create if not found.
     */
    private function handleUpdated(array $post, array $config, string $webhookId): array
    {
        $postId = (string)($post['id'] ?? '');

        if (!empty($postId)) {
            $existing = $this->findContentByWebhookId($postId);
            if ($existing) {
                return $this->updateContent($existing, $post, $config, $webhookId);
            }
        }

        $this->logger?->notice('Wortfreunde: post.updated but no existing record found, creating new.', [
            'post_id' => $postId,
        ]);
        return $this->createContent($post, $config, $webhookId);
    }

    private function createContent(array $post, array $config, string $webhookId): array
    {
        $contentData = $this->buildContentData($post, $config);
        $pageUid = $contentData['pid'];

        if ($pageUid <= 0) {
            throw new \InvalidArgumentException(
                'No target page configured. Configure "webhook.defaultPageUid" in extension settings.'
            );
        }

        $contentData['tstamp'] = time();
        $contentData['crdate'] = time();

        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $connection->insert('tt_content', $contentData);
        $newUid = (int)$connection->lastInsertId();

        if ($newUid <= 0) {
            throw new \RuntimeException('Failed to create tt_content record.');
        }

        $this->storeWebhookReference($newUid, $webhookId);

        return [
            'action' => 'created',
            'tt_content_uid' => $newUid,
            'page_uid' => $pageUid,
            'post_id' => $post['id'] ?? null,
            'webhook_id' => $webhookId,
        ];
    }

    private function updateContent(array $existing, array $post, array $config, string $webhookId): array
    {
        $contentData = $this->buildContentData($post, $config);
        unset($contentData['pid']); // Don't move existing record
        $contentData['tstamp'] = time();

        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $connection->update('tt_content', $contentData, ['uid' => $existing['uid']]);

        return [
            'action' => 'updated',
            'tt_content_uid' => $existing['uid'],
            'page_uid' => $existing['pid'],
            'post_id' => $post['id'] ?? null,
            'webhook_id' => $webhookId,
        ];
    }

    /**
     * Build tt_content field array from Wortfreunde post object.
     */
    private function buildContentData(array $post, array $config): array
    {
        $markdown = $post['body'] ?? '';

        if (empty(trim($markdown))) {
            throw new \InvalidArgumentException('Post body is empty.');
        }

        // Extract frontmatter if present (body from Wortfreunde may contain it)
        $parsed = $this->markdownConverter->extractFrontmatter($markdown);
        $markdownBody = $parsed['body'];

        // Title comes from the post object
        $title = $post['title'] ?? null;

        // If no title in post, try extracting from H1
        if (empty($title)) {
            $title = $this->markdownConverter->extractTitle($markdownBody);
            if ($title !== null) {
                $markdownBody = $this->markdownConverter->removeTitle($markdownBody);
            }
        }

        $htmlContent = $this->markdownConverter->convert($markdownBody);

        $pageUid = (int)($config['defaultPageUid'] ?? 0);

        $data = [
            'pid' => $pageUid,
            'CType' => $config['defaultContentType'] ?? 'text',
            'colPos' => (int)($config['defaultColPos'] ?? 0),
            'sys_language_uid' => (int)($config['defaultLanguageUid'] ?? 0),
            'header' => $title ?? 'Wortfreunde Post',
            'bodytext' => $htmlContent,
        ];

        // Teaser → subheader
        if (!empty($post['teaser'])) {
            $data['subheader'] = $post['teaser'];
        }

        // published_at → date
        if (!empty($post['published_at'])) {
            $timestamp = strtotime($post['published_at']);
            if ($timestamp !== false) {
                $data['date'] = $timestamp;
            }
        }

        return $data;
    }

    /**
     * Find an existing tt_content record by its Wortfreunde post ID.
     */
    private function findContentByWebhookId(string $webhookId): ?array
    {
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tx_wortfreundeconnector_webhook_log');

        $logEntry = $queryBuilder
            ->select('tt_content_uid')
            ->from('tx_wortfreundeconnector_webhook_log')
            ->where(
                $queryBuilder->expr()->eq(
                    'webhook_id',
                    $queryBuilder->createNamedParameter($webhookId)
                ),
                $queryBuilder->expr()->eq(
                    'status',
                    $queryBuilder->createNamedParameter('processed')
                ),
                $queryBuilder->expr()->gt('tt_content_uid', 0)
            )
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$logEntry || $logEntry['tt_content_uid'] <= 0) {
            return null;
        }

        $contentQueryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tt_content');

        return $contentQueryBuilder
            ->select('uid', 'pid')
            ->from('tt_content')
            ->where(
                $contentQueryBuilder->expr()->eq(
                    'uid',
                    $contentQueryBuilder->createNamedParameter((int)$logEntry['tt_content_uid'])
                )
            )
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }

    private function storeWebhookReference(int $ttContentUid, string $webhookId): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_wortfreundeconnector_webhook_log');
        $connection->update(
            'tx_wortfreundeconnector_webhook_log',
            ['tt_content_uid' => $ttContentUid],
            ['webhook_id' => $webhookId]
        );
    }

    private function logWebhook(string $webhookId, array $payload, string $status, string $event = '', string $deliveryId = ''): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_wortfreundeconnector_webhook_log');
        $connection->insert(
            'tx_wortfreundeconnector_webhook_log',
            [
                'pid' => 0,
                'tstamp' => time(),
                'crdate' => time(),
                'webhook_id' => $webhookId,
                'event_type' => $event,
                'delivery_id' => $deliveryId,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'page_uid' => 0,
            ]
        );
    }

    private function updateWebhookLog(string $webhookId, string $status, string $errorMessage = '', int $ttContentUid = 0): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_wortfreundeconnector_webhook_log');
        $updateData = [
            'tstamp' => time(),
            'status' => $status,
        ];

        if (!empty($errorMessage)) {
            $updateData['error_message'] = $errorMessage;
        }
        if ($ttContentUid > 0) {
            $updateData['tt_content_uid'] = $ttContentUid;
        }

        $connection->update(
            'tx_wortfreundeconnector_webhook_log',
            $updateData,
            ['webhook_id' => $webhookId]
        );
    }

    private function getConfiguration(): array
    {
        // Read from Registry (DB) first, fall back to ExtensionConfiguration
        $stored = $this->registry->get('wortfreunde_connector', 'settings', []);
        if (!empty($stored)) {
            return $stored;
        }

        try {
            $config = $this->extensionConfiguration->get('wortfreunde_connector');
            return $config['webhook'] ?? $config['webhook.'] ?? $config;
        } catch (\Throwable) {
            return [];
        }
    }

}
