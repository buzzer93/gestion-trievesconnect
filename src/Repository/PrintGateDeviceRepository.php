<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrintGateDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintGateDevice>
 */
class PrintGateDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintGateDevice::class);
    }

    public function findOneByComputerId(string $computerId): ?PrintGateDevice
    {
        return $this->findOneBy(['computerId' => $computerId]);
    }

    /**
     * Liste des postes pour le back-office. Pas de pagination : la V1 ne
     * vise qu'un ou deux postes (résumé technique §15) -- une pagination
     * serait une complexité anticipée sans besoin réel actuel. À revoir
     * si le nombre de postes gérés grandit significativement.
     *
     * @return PrintGateDevice[]
     */
    public function search(?string $query): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.hostname', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('d.hostname LIKE :q OR d.computerId LIKE :q OR d.displayName LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
