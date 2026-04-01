<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use MauticPlugin\MauticSyncDataBundle\Entity\Suppression;
use MauticPlugin\MauticSyncDataBundle\Service\StatsCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends AbstractFormController
{
    public function __construct(
        private readonly StatsCalculator $statsCalculator,
    ) {
    }

    public function indexAction(): Response
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return $this->accessDenied();
        }

        $stats     = $this->statsCalculator->getSummaryCards();
        $breakdown = $this->statsCalculator->getBreakdownData();
        $trend     = $this->statsCalculator->getTrendData('30d');
        $recent    = $this->statsCalculator->getRecentSuppressions(1, 10);
        $history   = $this->statsCalculator->getSyncHistory(1, 5);

        return $this->delegateView([
            'viewParameters' => [
                'stats'     => $stats,
                'breakdown' => $breakdown,
                'trend'     => $trend,
                'recent'    => $recent,
                'history'   => $history,
                'types'     => Suppression::ALL_TYPES,
            ],
            'contentTemplate' => '@MauticSyncData/Dashboard/index.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_syncdata_dashboard',
                'mauticContent' => 'syncdata',
                'route'         => $this->generateUrl('mautic_syncdata_dashboard'),
            ],
        ]);
    }

    public function statsAction(): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return new JsonResponse($this->statsCalculator->getSummaryCards());
    }

    public function chartDataAction(Request $request, string $type): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if ('breakdown' === $type) {
            return new JsonResponse($this->statsCalculator->getBreakdownData());
        }

        if ('trend' === $type) {
            $period         = $request->query->get('period', '30d');
            $suppressionType = $request->query->get('suppression_type');

            return new JsonResponse($this->statsCalculator->getTrendData($period, $suppressionType));
        }

        return new JsonResponse(['error' => 'Invalid chart type'], 400);
    }

    public function suppressionsAction(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $page    = max(1, (int) $request->query->get('page', 1));
        $limit   = min(100, max(1, (int) $request->query->get('limit', 25)));
        $filters = [
            'type'     => $request->query->get('type'),
            'email'    => $request->query->get('email'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo'   => $request->query->get('dateTo'),
        ];

        $filters = array_filter($filters);

        return new JsonResponse($this->statsCalculator->getRecentSuppressions($page, $limit, $filters));
    }

    public function historyAction(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('plugin:syncdata:dashboard:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        return new JsonResponse($this->statsCalculator->getSyncHistory($page, $limit));
    }

    public function exportAction(Request $request): Response
    {
        if (!$this->security->isGranted('plugin:syncdata:settings:manage')) {
            return $this->accessDenied();
        }

        $filters = [
            'type'     => $request->query->get('type'),
            'email'    => $request->query->get('email'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo'   => $request->query->get('dateTo'),
        ];
        $filters = array_filter($filters);

        $response = new StreamedResponse(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'Type', 'Reason', 'Status', 'SendGrid Date', 'Synced Date', 'Action', 'Contact ID']);

            $page = 1;
            do {
                $result = $this->statsCalculator->getRecentSuppressions($page, 100, $filters);
                foreach ($result['items'] as $item) {
                    fputcsv($handle, [
                        $item['email'],
                        $item['type_label'],
                        $item['reason'] ?? '',
                        $item['status'] ?? '',
                        $item['sendgrid_date'],
                        $item['synced_date'],
                        $item['action'],
                        $item['contact_id'] ?? '',
                    ]);
                }
                ++$page;
            } while ($page <= $result['pages']);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="syncdata_suppressions_'.date('Y-m-d').'.csv"');

        return $response;
    }
}
