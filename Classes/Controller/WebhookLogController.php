<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;

/**
 * Backend module controller for viewing webhook logs and managing settings.
 */
class WebhookLogController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly UriBuilder $uriBuilder,
        private readonly Registry $registry,
    ) {}

    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tx_wortfreundeconnector_webhook_log');

        $logs = $queryBuilder
            ->select('*')
            ->from('tx_wortfreundeconnector_webhook_log')
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(100)
            ->executeQuery()
            ->fetchAllAssociative();

        $stats = [
            'total' => count($logs),
            'processed' => count(array_filter($logs, fn($l) => $l['status'] === 'processed')),
            'pending' => count(array_filter($logs, fn($l) => $l['status'] === 'pending')),
            'error' => count(array_filter($logs, fn($l) => $l['status'] === 'error')),
        ];

        $moduleTemplate->assignMultiple([
            'logs' => $logs,
            'stats' => $stats,
            'activeTab' => 'logs',
        ]);

        return $moduleTemplate->renderResponse('Backend/WebhookLog');
    }

    public function settingsAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $settings = $this->getSettings();

        $saved = (bool)($request->getQueryParams()['saved'] ?? false);

        $moduleTemplate->assignMultiple([
            'settings' => $settings,
            'activeTab' => 'settings',
            'saved' => $saved,
        ]);

        return $moduleTemplate->renderResponse('Backend/Settings');
    }

    public function saveSettingsAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = $body['settings'] ?? [];

        $settings = [
            'secret' => (string)($input['secret'] ?? ''),
            'defaultPageUid' => (int)($input['defaultPageUid'] ?? 0),
            'defaultLanguageUid' => (int)($input['defaultLanguageUid'] ?? 0),
            'defaultColPos' => (int)($input['defaultColPos'] ?? 0),
            'defaultContentType' => (string)($input['defaultContentType'] ?? 'text'),
            'enableLogging' => (bool)($input['enableLogging'] ?? false),
        ];

        $this->registry->set('wortfreunde_connector', 'settings', $settings);

        $uri = $this->uriBuilder->buildUriFromRoute('wortfreunde.settings', ['saved' => 1]);
        return new \TYPO3\CMS\Core\Http\RedirectResponse($uri);
    }

    private function getSettings(): array
    {
        // Read from Registry (DB) first, fall back to ExtensionConfiguration
        $stored = $this->registry->get('wortfreunde_connector', 'settings', []);
        if (!empty($stored)) {
            return $stored;
        }

        try {
            $config = $this->extensionConfiguration->get('wortfreunde_connector');
        } catch (\Throwable) {
            $config = [];
        }

        return [
            'secret' => $config['webhook.']['secret'] ?? $config['webhook']['secret'] ?? '',
            'defaultPageUid' => (int)($config['webhook.']['defaultPageUid'] ?? $config['webhook']['defaultPageUid'] ?? 0),
            'defaultLanguageUid' => (int)($config['webhook.']['defaultLanguageUid'] ?? $config['webhook']['defaultLanguageUid'] ?? 0),
            'defaultColPos' => (int)($config['webhook.']['defaultColPos'] ?? $config['webhook']['defaultColPos'] ?? 0),
            'defaultContentType' => $config['webhook.']['defaultContentType'] ?? $config['webhook']['defaultContentType'] ?? 'text',
            'enableLogging' => (bool)($config['webhook.']['enableLogging'] ?? $config['webhook']['enableLogging'] ?? true),
        ];
    }
}
