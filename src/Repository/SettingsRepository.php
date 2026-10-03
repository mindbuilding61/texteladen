<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function getOrCreate(): Settings
    {
        $settings = $this->find(1);
        if ($settings === null) {
            $settings = new Settings();
            $em = $this->getEntityManager();
            $em->persist($settings);
            $em->flush();
        }
        return $settings;
    }

    public function nextInvoiceNumber(): string
    {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();
        try {
            $settings = $this->getOrCreate();
            $num = $settings->getNextInvoiceNumber();
            $prefix = $settings->getInvoiceNumberPrefix();
            $year = (new \DateTimeImmutable())->format('Y');
            $formatted = sprintf('%s%s-%04d', $prefix, $year, $num);
            $settings->setNextInvoiceNumber($num + 1);
            $em->flush();
            $em->getConnection()->commit();
            return $formatted;
        } catch (\Throwable $e) {
            $em->getConnection()->rollBack();
            throw $e;
        }
    }
}
