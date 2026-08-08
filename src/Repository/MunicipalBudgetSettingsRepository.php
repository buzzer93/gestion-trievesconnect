<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MunicipalBudgetSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MunicipalBudgetSettings>
 */
class MunicipalBudgetSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MunicipalBudgetSettings::class);
    }

    /**
     * La ligne singleton est seedée par la migration ; le fallback de
     * création ici couvre uniquement le cas défensif où elle aurait été
     * supprimée manuellement en base.
     */
    public function getSettings(): MunicipalBudgetSettings
    {
        $settings = $this->find(MunicipalBudgetSettings::SINGLETON_ID);

        if (null === $settings) {
            $settings = new MunicipalBudgetSettings();
            $em = $this->getEntityManager();
            $em->persist($settings);
            $em->flush();
        }

        return $settings;
    }
}
