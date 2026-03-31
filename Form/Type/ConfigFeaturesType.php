<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Form\Type;

use MauticPlugin\MauticSendGridSyncBundle\Entity\Suppression;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigFeaturesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('enabled_types', ChoiceType::class, [
            'label'    => 'mautic.sendgridsync.settings.enabled_types',
            'choices'  => [
                'mautic.sendgridsync.type.bounce'             => Suppression::TYPE_BOUNCE,
                'mautic.sendgridsync.type.spam_report'        => Suppression::TYPE_SPAM_REPORT,
                'mautic.sendgridsync.type.block'              => Suppression::TYPE_BLOCK,
                'mautic.sendgridsync.type.invalid_email'      => Suppression::TYPE_INVALID_EMAIL,
                'mautic.sendgridsync.type.global_unsubscribe' => Suppression::TYPE_GLOBAL_UNSUBSCRIBE,
                'mautic.sendgridsync.type.group_unsubscribe'  => Suppression::TYPE_GROUP_UNSUBSCRIBE,
            ],
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'data'     => Suppression::ALL_TYPES,
        ]);

        $builder->add('sync_interval', ChoiceType::class, [
            'label'   => 'mautic.sendgridsync.settings.sync_interval',
            'choices' => [
                '5 minutes'  => 5,
                '15 minutes' => 15,
                '30 minutes' => 30,
                '1 hour'     => 60,
                '6 hours'    => 360,
                '12 hours'   => 720,
                '24 hours'   => 1440,
            ],
            'data' => 15,
        ]);

        $builder->add('initial_sync_range', ChoiceType::class, [
            'label'   => 'mautic.sendgridsync.settings.initial_range',
            'choices' => [
                '7 days'   => 7,
                '30 days'  => 30,
                '90 days'  => 90,
                'All time' => 0,
            ],
            'data' => 30,
        ]);

        $builder->add('default_action_mode', ChoiceType::class, [
            'label'   => 'mautic.sendgridsync.settings.action_mode',
            'choices' => [
                'mautic.sendgridsync.settings.action_dnc'     => 'dnc',
                'mautic.sendgridsync.settings.action_segment' => 'segment',
            ],
            'data' => 'dnc',
        ]);

        $builder->add('notification_email', EmailType::class, [
            'label'    => 'mautic.sendgridsync.settings.notification_email',
            'required' => false,
            'attr'     => [
                'class'       => 'form-control',
                'placeholder' => 'admin@example.com',
            ],
        ]);

        $builder->add('spike_threshold', IntegerType::class, [
            'label'    => 'mautic.sendgridsync.settings.spike_threshold',
            'required' => false,
            'data'     => 50,
            'attr'     => [
                'class' => 'form-control',
                'min'   => 1,
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sendgridsync_config_features';
    }
}
