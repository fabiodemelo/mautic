<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class SendGridSyncPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);

        $this->addStandardPermissions('dashboard');
        $this->addStandardPermissions('settings');
    }

    public function getName(): string
    {
        return 'sendgridsync';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addStandardFormFields('sendgridsync', 'dashboard', $builder, $data);
        $this->addStandardFormFields('sendgridsync', 'settings', $builder, $data);
    }
}
