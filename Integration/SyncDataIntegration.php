<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;

class SyncDataIntegration extends BasicIntegration implements BasicInterface, ConfigFormInterface, ConfigFormAuthInterface, ConfigFormFeaturesInterface
{
    use ConfigurationTrait;
    use DefaultConfigFormTrait;

    public const NAME         = 'SyncData';
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
        return 'plugins/MauticSyncDataBundle/Assets/img/syncdata.jpg';
    }

    public function getAuthConfigFormName(): string
    {
        return \MauticPlugin\MauticSyncDataBundle\Form\Type\ConfigAuthType::class;
    }

    public function getFeatureSettingsConfigFormName(): ?string
    {
        return \MauticPlugin\MauticSyncDataBundle\Form\Type\ConfigFeaturesType::class;
    }
}
