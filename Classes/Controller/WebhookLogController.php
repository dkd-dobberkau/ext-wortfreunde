<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Backend module controller for viewing and managing webhook logs.
 */
class WebhookLogController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        // Fetch recent webhook logs
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tx_wortfreundeconnector_webhook_log');

        $logs = $queryBuilder
            ->select('*')
            ->from('tx_wortfreundeconnector_webhook_log')
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(100)
            ->executeQuery()
            ->fetchAllAssociative();

        // Calculate statistics
        $stats = [
            'total' => count($logs),
            'processed' => count(array_filter($logs, fn($l) => $l['status'] === 'processed')),
            'pending' => count(array_filter($logs, fn($l) => $l['status'] === 'pending')),
            'error' => count(array_filter($logs, fn($l) => $l['status'] === 'error')),
        ];

        $moduleTemplate->assignMultiple([
            'logs' => $logs,
            'stats' => $stats,
        ]);

        return $moduleTemplate->renderResponse('Backend/WebhookLog');
    }
}
