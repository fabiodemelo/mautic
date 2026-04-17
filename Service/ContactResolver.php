<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSyncDataBundle\Service;

use Doctrine\DBAL\ArrayParameterType;
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

        $emails = array_map(fn (string $e) => strtolower(trim($e)), $emails);
        $emails = array_values(array_unique($emails));
        $result = array_fill_keys($emails, null);

        $query = $this->entityManager->createQuery(
            'SELECT l FROM '.Lead::class.' l WHERE l.email IN (:emails)'
        );
        $query->setParameter('emails', $emails, ArrayParameterType::STRING);

        /** @var Lead[] $contacts */
        $contacts = $query->getResult();

        foreach ($contacts as $contact) {
            $email = $contact->getEmail();
            if (null === $email) {
                continue;
            }
            $key = strtolower($email);
            if (array_key_exists($key, $result)) {
                $result[$key] = $contact;
            }
        }

        return $result;
    }
}
