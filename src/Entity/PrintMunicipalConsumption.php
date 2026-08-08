<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintMunicipalConsumptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne par impression payée (en tout ou partie) par le crédit mairie
 * d'une Association. Sert de justificatif détaillé pour la facturation
 * trimestrielle : les totaux par trimestre et par type d'impression sont
 * calculés par requête sur cette table (pas de compteur agrégé séparé, une
 * seule source de vérité). Ne représente jamais le contenu du document,
 * comme PrintJobPayload dont elle reprend les champs pertinents.
 */
#[ORM\Entity(repositoryClass: PrintMunicipalConsumptionRepository::class)]
class PrintMunicipalConsumption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Association::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Association $association;

    #[ORM\Column(type: Types::INTEGER)]
    private int $printJobId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pageCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $copies;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $colorMode;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $paperSize;

    /**
     * Part réellement prélevée sur le crédit mairie, en centimes -- peut
     * être inférieure au coût total de l'impression si le solde mairie a
     * basculé en cours de job sur le crédit personnel de l'association
     * (cf. AssociationRepository::debitForPrintJob()).
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $amountSpentCents;

    public function __construct(
        Association $association,
        int $printJobId,
        int $pageCount,
        int $copies,
        ?string $colorMode,
        ?string $paperSize,
        int $amountSpentCents,
    ) {
        $this->association = $association;
        $this->printJobId = $printJobId;
        $this->pageCount = $pageCount;
        $this->copies = $copies;
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->amountSpentCents = $amountSpentCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssociation(): Association
    {
        return $this->association;
    }

    public function getPrintJobId(): int
    {
        return $this->printJobId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function getCopies(): int
    {
        return $this->copies;
    }

    public function getColorMode(): ?string
    {
        return $this->colorMode;
    }

    public function getPaperSize(): ?string
    {
        return $this->paperSize;
    }

    public function getAmountSpentCents(): int
    {
        return $this->amountSpentCents;
    }
}
