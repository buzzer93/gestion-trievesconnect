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
}
