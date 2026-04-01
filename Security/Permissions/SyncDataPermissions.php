<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class SyncDataPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);

        $this->addStandardPermissions('dashboard');
        $this->addStandardPermissions('settings');
    }

    public function getName(): string
    {
        return 'syncdata';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addStandardFormFields('syncdata', 'dashboard', $builder, $data);
        $this->addStandardFormFields('syncdata', 'settings', $builder, $data);
    }
}
