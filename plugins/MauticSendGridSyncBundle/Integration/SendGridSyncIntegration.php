<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class SendGridSyncIntegration extends BasicIntegration implements BasicInterface
{
    public const NAME         = 'SendGridSync';
    public const DISPLAY_NAME = 'SendGrid Suppression Sync';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/MauticSendGridSyncBundle/Assets/img/sendgrid.png';
    }
}
