<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSendGridSyncBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;

class ContactResolver
{
    public function __construct(
        private readonly LeadModel $leadModel,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveByEmail(string $email): ?Lead
    {
        $repo = $this->leadModel->getRepository();

        return $repo->getContactsByEmail(strtolower(trim($email)))[0] ?? null;
    }

    /**
     * Batch resolve contacts by email addresses.
     *
     * @param string[] $emails
     *
     * @return array<string, Lead|null> Keyed by lowercase email
     */
    public function resolveByEmails(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $emails     = array_map(fn (string $e) => strtolower(trim($e)), $emails);
        $emails     = array_unique($emails);
        $result     = array_fill_keys($emails, null);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lead::class, 'l')
            ->where('LOWER(l.email) IN (:emails)')
            ->setParameter('emails', $emails);

        $contacts = $qb->getQuery()->getResult();

        foreach ($contacts as $contact) {
            $contactEmail = strtolower($contact->getEmail());
            if (isset($result[$contactEmail])) {
                $result[$contactEmail] = $contact;
            }
        }

        return $result;
    }
}
