<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuppressionRepository::class)]
#[ORM\Table(name: 'plugin_sendgrid_suppressions')]
#[ORM\Index(columns: ['email'], name: 'idx_sgs_email')]
#[ORM\Index(columns: ['suppression_type'], name: 'idx_sgs_type')]
#[ORM\Index(columns: ['synced_at'], name: 'idx_sgs_synced_at')]
#[ORM\Index(columns: ['mautic_contact_id'], name: 'idx_sgs_contact')]
#[ORM\UniqueConstraint(name: 'uniq_sgs_email_type_date', columns: ['email', 'suppression_type', 'sendgrid_created_at'])]
class Suppression
{
    public const TYPE_BOUNCE             = 'bounce';
    public const TYPE_SPAM_REPORT        = 'spam_report';
    public const TYPE_BLOCK              = 'block';
    public const TYPE_INVALID_EMAIL      = 'invalid_email';
    public const TYPE_GLOBAL_UNSUBSCRIBE = 'global_unsubscribe';
    public const TYPE_GROUP_UNSUBSCRIBE  = 'group_unsubscribe';

    public const ALL_TYPES = [
        self::TYPE_BOUNCE,
        self::TYPE_SPAM_REPORT,
        self::TYPE_BLOCK,
        self::TYPE_INVALID_EMAIL,
        self::TYPE_GLOBAL_UNSUBSCRIBE,
        self::TYPE_GROUP_UNSUBSCRIBE,
    ];

    public const ACTION_DNC       = 'dnc';
    public const ACTION_SEGMENT   = 'segment';
    public const ACTION_UNMATCHED = 'unmatched';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(name: 'suppression_type', type: Types::STRING, length: 30)]
    private string $suppressionType;

    #[ORM\Column(name: 'sendgrid_reason', type: Types::TEXT, nullable: true)]
    private ?string $sendgridReason = null;

    #[ORM\Column(name: 'sendgrid_status', type: Types::STRING, length: 50, nullable: true)]
    private ?string $sendgridStatus = null;

    #[ORM\Column(name: 'sendgrid_created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $sendgridCreatedAt;

    #[ORM\Column(name: 'sendgrid_group_id', type: Types::INTEGER, nullable: true)]
    private ?int $sendgridGroupId = null;

    #[ORM\Column(name: 'sendgrid_group_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $sendgridGroupName = null;

    #[ORM\Column(name: 'mautic_contact_id', type: Types::INTEGER, nullable: true)]
    private ?int $mauticContactId = null;

    #[ORM\Column(name: 'action_taken', type: Types::STRING, length: 20)]
    private string $actionTaken = self::ACTION_UNMATCHED;

    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $syncedAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->syncedAt  = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getSuppressionType(): string
    {
        return $this->suppressionType;
    }

    public function setSuppressionType(string $suppressionType): self
    {
        $this->suppressionType = $suppressionType;

        return $this;
    }

    public function getSendgridReason(): ?string
    {
        return $this->sendgridReason;
    }

    public function setSendgridReason(?string $sendgridReason): self
    {
        $this->sendgridReason = $sendgridReason;

        return $this;
    }

    public function getSendgridStatus(): ?string
    {
        return $this->sendgridStatus;
    }

    public function setSendgridStatus(?string $sendgridStatus): self
    {
        $this->sendgridStatus = $sendgridStatus;

        return $this;
    }

    public function getSendgridCreatedAt(): \DateTimeInterface
    {
        return $this->sendgridCreatedAt;
    }

    public function setSendgridCreatedAt(\DateTimeInterface $sendgridCreatedAt): self
    {
        $this->sendgridCreatedAt = $sendgridCreatedAt;

        return $this;
    }

    public function getSendgridGroupId(): ?int
    {
        return $this->sendgridGroupId;
    }

    public function setSendgridGroupId(?int $sendgridGroupId): self
    {
        $this->sendgridGroupId = $sendgridGroupId;

        return $this;
    }

    public function getSendgridGroupName(): ?string
    {
        return $this->sendgridGroupName;
    }

    public function setSendgridGroupName(?string $sendgridGroupName): self
    {
        $this->sendgridGroupName = $sendgridGroupName;

        return $this;
    }

    public function getMauticContactId(): ?int
    {
        return $this->mauticContactId;
    }

    public function setMauticContactId(?int $mauticContactId): self
    {
        $this->mauticContactId = $mauticContactId;

        return $this;
    }

    public function getActionTaken(): string
    {
        return $this->actionTaken;
    }

    public function setActionTaken(string $actionTaken): self
    {
        $this->actionTaken = $actionTaken;

        return $this;
    }

    public function getSyncedAt(): \DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeInterface $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_BOUNCE             => 'Bounce',
            self::TYPE_SPAM_REPORT        => 'Spam Report',
            self::TYPE_BLOCK              => 'Block',
            self::TYPE_INVALID_EMAIL      => 'Invalid Email',
            self::TYPE_GLOBAL_UNSUBSCRIBE => 'Global Unsubscribe',
            self::TYPE_GROUP_UNSUBSCRIBE  => 'Group Unsubscribe',
            default                       => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
