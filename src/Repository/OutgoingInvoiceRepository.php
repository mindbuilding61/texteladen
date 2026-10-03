<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OutgoingInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutgoingInvoice>
 */
class OutgoingInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutgoingInvoice::class);
    }

    /** @return OutgoingInvoice[] */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.issueDate', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
