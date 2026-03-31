<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SyncLogRepository::class)]
#[ORM\Table(name: 'plugin_sendgrid_sync_log')]
#[ORM\Index(columns: ['status'], name: 'idx_sgsl_status')]
#[ORM\Index(columns: ['started_at'], name: 'idx_sgsl_started_at')]
class SyncLog
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED  = 'failed';

    public const TYPE_INCREMENTAL = 'incremental';
    public const TYPE_FULL        = 'full';
    public const TYPE_MANUAL      = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'sync_type', type: Types::STRING, length: 20)]
    private string $syncType = self::TYPE_INCREMENTAL;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $startedAt;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_RUNNING;

    #[ORM\Column(name: 'records_fetched', type: Types::INTEGER)]
    private int $recordsFetched = 0;

    #[ORM\Column(name: 'records_added', type: Types::INTEGER)]
    private int $recordsAdded = 0;

    #[ORM\Column(name: 'records_skipped', type: Types::INTEGER)]
    private int $recordsSkipped = 0;

    #[ORM\Column(name: 'records_unmatched', type: Types::INTEGER)]
    private int $recordsUnmatched = 0;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'suppression_breakdown', type: Types::JSON, nullable: true)]
    private ?array $suppressionBreakdown = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->startedAt = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyncType(): string
    {
        return $this->syncType;
    }

    public function setSyncType(string $syncType): self
    {
        $this->syncType = $syncType;

        return $this;
    }

    public function getStartedAt(): \DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRecordsFetched(): int
    {
        return $this->recordsFetched;
    }

    public function setRecordsFetched(int $recordsFetched): self
    {
        $this->recordsFetched = $recordsFetched;

        return $this;
    }

    public function getRecordsAdded(): int
    {
        return $this->recordsAdded;
    }

    public function setRecordsAdded(int $recordsAdded): self
    {
        $this->recordsAdded = $recordsAdded;

        return $this;
    }

    public function getRecordsSkipped(): int
    {
        return $this->recordsSkipped;
    }

    public function setRecordsSkipped(int $recordsSkipped): self
    {
        $this->recordsSkipped = $recordsSkipped;

        return $this;
    }

    public function getRecordsUnmatched(): int
    {
        return $this->recordsUnmatched;
    }

    public function setRecordsUnmatched(int $recordsUnmatched): self
    {
        $this->recordsUnmatched = $recordsUnmatched;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getSuppressionBreakdown(): ?array
    {
        return $this->suppressionBreakdown;
    }

    public function setSuppressionBreakdown(?array $suppressionBreakdown): self
    {
        $this->suppressionBreakdown = $suppressionBreakdown;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function markCompleted(): self
    {
        $this->completedAt = new \DateTime();
        $this->status      = self::STATUS_SUCCESS;

        return $this;
    }

    public function markFailed(string $errorMessage): self
    {
        $this->completedAt  = new \DateTime();
        $this->status       = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function markPartial(string $errorMessage): self
    {
        $this->completedAt  = new \DateTime();
        $this->status       = self::STATUS_PARTIAL;
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        if (null === $this->completedAt) {
            return null;
        }

        return $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }
}
