<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Service;

use Mautic\CoreBundle\Helper\MailHelper;
use MauticPlugin\MauticSendGridSyncBundle\Entity\Suppression;
use MauticPlugin\MauticSendGridSyncBundle\Entity\SyncLog;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private readonly MailHelper $mailHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendSyncFailureNotification(SyncLog $log, string $recipientEmail): void
    {
        try {
            $mailer = $this->mailHelper;
            $mailer->setTo([$recipientEmail => $recipientEmail]);
            $mailer->setSubject('[SendGrid Sync] Sync Failed');
            $mailer->setBody(
                '<h2>SendGrid Suppression Sync Failed</h2>'.
                '<p><strong>Started:</strong> '.$log->getStartedAt()->format('Y-m-d H:i:s').'</p>'.
                '<p><strong>Type:</strong> '.$log->getSyncType().'</p>'.
                '<p><strong>Error:</strong> '.htmlspecialchars($log->getErrorMessage() ?? 'Unknown error').'</p>'.
                '<p>Please check the plugin dashboard for details.</p>'
            );
            $mailer->send();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send sync failure notification', ['error' => $e->getMessage()]);
        }
    }

    public function sendSpikeAlert(string $type, int $count, int $threshold, string $recipientEmail): void
    {
        try {
            $typeLabel = Suppression::getTypeLabel($type);
            $mailer    = $this->mailHelper;
            $mailer->setTo([$recipientEmail => $recipientEmail]);
            $mailer->setSubject("[SendGrid Sync] Spike Alert: {$typeLabel}");
            $mailer->setBody(
                '<h2>Suppression Spike Detected</h2>'.
                "<p>A spike in <strong>{$typeLabel}</strong> suppressions was detected.</p>".
                "<p><strong>Count:</strong> {$count} (threshold: {$threshold})</p>".
                '<p><strong>Time:</strong> '.(new \DateTime())->format('Y-m-d H:i:s').'</p>'.
                '<p>Please review your recent campaigns and list quality.</p>'
            );
            $mailer->send();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send spike alert', ['error' => $e->getMessage()]);
        }
    }
}
