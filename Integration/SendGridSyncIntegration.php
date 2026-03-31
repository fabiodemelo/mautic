<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;

class SendGridSyncIntegration extends BasicIntegration implements BasicInterface, ConfigFormAuthInterface, ConfigFormFeaturesInterface
{
    use ConfigurationTrait;
    use DefaultConfigFormTrait;

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

    public function getConfigFormName(): ?string
    {
        return \MauticPlugin\MauticSendGridSyncBundle\Form\Type\ConfigAuthType::class;
    }

    public function getFeatureSettingsConfigFormName(): ?string
    {
        return \MauticPlugin\MauticSendGridSyncBundle\Form\Type\ConfigFeaturesType::class;
    }
}
