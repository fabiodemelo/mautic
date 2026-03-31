<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('api_key', PasswordType::class, [
            'label'      => 'mautic.sendgridsync.settings.api_key',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'       => 'form-control',
                'placeholder' => 'SG.xxxxxxxxxx',
                'autocomplete' => 'off',
            ],
            'required' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sendgridsync_config_auth';
    }
}
