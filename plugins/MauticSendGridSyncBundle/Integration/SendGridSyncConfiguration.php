<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Integration;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\MauticSendGridSyncBundle\Form\Type\ConfigAuthType;
use MauticPlugin\MauticSendGridSyncBundle\Form\Type\ConfigFeaturesType;

class SendGridSyncConfiguration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;

    public function getAuthConfigFormName(): string
    {
        return ConfigAuthType::class;
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return ConfigFeaturesType::class;
    }
}
