<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;

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

        try {
            $config = $this->extensionConfiguration->get('wortfreunde_connector');
        } catch (\Throwable) {
            $config = [];
        }

        $settings = [
            'secret' => $config['webhook.']['secret'] ?? $config['webhook']['secret'] ?? '',
            'defaultPageUid' => (int)($config['webhook.']['defaultPageUid'] ?? $config['webhook']['defaultPageUid'] ?? 0),
            'defaultLanguageUid' => (int)($config['webhook.']['defaultLanguageUid'] ?? $config['webhook']['defaultLanguageUid'] ?? 0),
            'defaultColPos' => (int)($config['webhook.']['defaultColPos'] ?? $config['webhook']['defaultColPos'] ?? 0),
            'defaultContentType' => $config['webhook.']['defaultContentType'] ?? $config['webhook']['defaultContentType'] ?? 'text',
            'enableLogging' => (bool)($config['webhook.']['enableLogging'] ?? $config['webhook']['enableLogging'] ?? true),
        ];

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
        $settings = $body['settings'] ?? [];

        $config = [
            'webhook.' => [
                'secret' => (string)($settings['secret'] ?? ''),
                'defaultPageUid' => (int)($settings['defaultPageUid'] ?? 0),
                'defaultLanguageUid' => (int)($settings['defaultLanguageUid'] ?? 0),
                'defaultColPos' => (int)($settings['defaultColPos'] ?? 0),
                'defaultContentType' => (string)($settings['defaultContentType'] ?? 'text'),
                'enableLogging' => (bool)($settings['enableLogging'] ?? false),
            ],
        ];

        $this->extensionConfiguration->set('wortfreunde_connector', $config);

        $uri = $this->uriBuilder->buildUriFromRoute('wortfreunde.settings', ['saved' => 1]);
        return new \TYPO3\CMS\Core\Http\RedirectResponse($uri);
    }
}
