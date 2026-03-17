<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Middleware;

use Wortfreunde\WortfreundeConnector\Service\WebhookProcessorService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * PSR-15 Middleware that intercepts incoming webhook requests from wortfreunde.ch
 *
 * Listens on: POST /wortfreunde/webhook
 * Verifies signature using HMAC-SHA256 over "{timestamp}.{body}"
 * as documented at https://wortfreunde.ch/docs/api-reference/webhooks
 */
class WebhookMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const WEBHOOK_PATH = '/wortfreunde/webhook';

    public function __construct(
        private readonly WebhookProcessorService $webhookProcessor,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!str_ends_with(rtrim($path, '/'), self::WEBHOOK_PATH)) {
            return $handler->handle($request);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->jsonResponse(['error' => 'Method not allowed. Use POST.'], 405);
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'application/json')) {
            return $this->jsonResponse(['error' => 'Content-Type must be application/json.'], 415);
        }

        $body = (string)$request->getBody();
        if (empty($body)) {
            return $this->jsonResponse(['error' => 'Empty request body.'], 400);
        }

        if (!$this->verifySignature($request, $body)) {
            $this->logger?->warning('Wortfreunde webhook: Invalid signature', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);
            return $this->jsonResponse(['error' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonResponse(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        // Extract Wortfreunde-specific headers
        $event = $request->getHeaderLine('X-Wortfreunde-Event');
        $deliveryId = $request->getHeaderLine('X-Wortfreunde-Delivery');

        try {
            $result = $this->webhookProcessor->process($payload, $event, $deliveryId);

            $this->logger?->info('Wortfreunde webhook: Processed successfully', [
                'event' => $event,
                'delivery_id' => $deliveryId,
                'tt_content_uid' => $result['tt_content_uid'] ?? 0,
                'page_uid' => $result['page_uid'] ?? 0,
            ]);

            $statusCode = ($result['action'] ?? '') === 'created' ? 201 : 200;

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Webhook processed successfully.',
                'data' => $result,
            ], $statusCode);
        } catch (\InvalidArgumentException $e) {
            $this->logger?->warning('Wortfreunde webhook: Validation error', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return $this->jsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->logger?->error('Wortfreunde webhook: Processing error', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->jsonResponse(['error' => 'Internal processing error.'], 500);
        }
    }

    /**
     * Verify HMAC-SHA256 signature from X-Wortfreunde-Signature header.
     *
     * Wortfreunde signs the concatenation of timestamp and body: "{timestamp}.{body}"
     * The timestamp is sent in X-Wortfreunde-Timestamp header.
     */
    private function verifySignature(ServerRequestInterface $request, string $body): bool
    {
        try {
            $config = $this->extensionConfiguration->get('wortfreunde_connector');
        } catch (\Throwable) {
            $config = [];
        }

        $secret = $config['webhook']['secret'] ?? $config['webhook.']['secret'] ?? '';

        if (empty($secret)) {
            $this->logger?->notice('Wortfreunde webhook: No secret configured, skipping signature verification.');
            return true;
        }

        $signature = $request->getHeaderLine('X-Wortfreunde-Signature');
        $timestamp = $request->getHeaderLine('X-Wortfreunde-Timestamp');

        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        // Wortfreunde signs "{timestamp}.{body}"
        $signedPayload = $timestamp . '.' . $body;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }

    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $body = $this->streamFactory->createStream(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
