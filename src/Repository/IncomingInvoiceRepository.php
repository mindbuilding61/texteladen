<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IncomingInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IncomingInvoice>
 */
class IncomingInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncomingInvoice::class);
    }

    public function findBySha256(string $hash): ?IncomingInvoice
    {
        return $this->findOneBy(['sha256' => $hash]);
    }

    /** @return IncomingInvoice[] */
    public function findRecent(int $limit = 200): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
