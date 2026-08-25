<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrintGateDevice;
use App\Entity\PrintTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintTransaction>
 */
class PrintTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintTransaction::class);
    }

    public function findByDeviceAndJobId(PrintGateDevice $device, int $jobId): ?PrintTransaction
    {
        return $this->findOneBy(['printGateDevice' => $device, 'jobId' => $jobId]);
    }

    /**
     * Persiste une transaction déjà construite (avec ses lignes) dans une
     * transaction DB explicite. C'est la contrainte UNIQUE
     * (printgate_device_id, job_id) portée par PrintTransaction, pas cette
     * méthode, qui garantit l'atomicité sous concurrence : si une requête
     * concurrente a déjà inséré la même paire (poste, jobId) entre la
     * vérification faite par l'appelant et ce flush(), la contrainte
     * échoue ici -- on rattrape l'exception, on relit la transaction déjà
     * commitée par l'autre requête, et on la renvoie au lieu d'échouer :
     * la demande reste idempotente même dans la fenêtre de course.
     *
     * L'appelant (PrintPolicyEvaluator) a déjà débité les soldes en
     * mémoire sur l'entité Customer/Association avant d'appeler cette
     * méthode : le flush() ci-dessous persiste la transaction ET les
     * soldes modifiés en une seule opération atomique.
     */
    public function recordCharge(PrintTransaction $transaction): PrintTransaction
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        $connection->beginTransaction();
        try {
            $em->persist($transaction);
            $em->flush();
            $connection->commit();

            return $transaction;
        } catch (UniqueConstraintViolationException $e) {
            $connection->rollBack();
            $em->clear();

            $device = $transaction->getPrintGateDevice();
            $jobId = $transaction->getJobId();

            if (null !== $device && null !== $jobId) {
                $existing = $this->findByDeviceAndJobId($device, $jobId);
                if (null !== $existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }
}
