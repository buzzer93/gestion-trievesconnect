<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintMunicipalConsumptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne par impression payée (en tout ou partie) par une Association,
 * qu'elle soit financée par le crédit mairie ou par le crédit personnel de
 * l'association (cf. $fundingSource) -- une impression qui bascule d'un
 * crédit à l'autre en cours de job produit deux lignes distinctes (cf.
 * AssociationRepository::debitForPrintJob()). Sert de justificatif détaillé
 * pour la facturation trimestrielle mairie (lignes MUNICIPAL uniquement) et
 * pour l'historique complet affiché sur la fiche association (les deux
 * sources, séparées) : les totaux sont calculés par requête sur cette
 * table (pas de compteur agrégé séparé, une seule source de vérité). Ne
 * représente jamais le contenu du document, comme PrintJobPayload dont elle
 * reprend les champs pertinents.
 *
 * Conserve son nom historique "PrintMunicipalConsumption" bien qu'elle
 * couvre désormais aussi les impressions payées sur crédit personnel :
 * c'est la même notion de justificatif d'impression pour une association,
 * seule la source de financement change.
 */
#[ORM\Entity(repositoryClass: PrintMunicipalConsumptionRepository::class)]
class PrintMunicipalConsumption
{
    public const SOURCE_MUNICIPAL = 'MUNICIPAL';
    public const SOURCE_PERSONAL = 'PERSONAL';

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
     * Part réellement prélevée sur la source indiquée par $fundingSource,
     * en centimes -- peut être inférieure au coût total de l'impression si
     * le solde mairie a basculé en cours de job sur le crédit personnel de
     * l'association (cf. AssociationRepository::debitForPrintJob()).
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $amountSpentCents;

    /**
     * self::SOURCE_MUNICIPAL ou self::SOURCE_PERSONAL -- pas d'enum PHP
     * natif, pour rester cohérent avec le reste de PrintGate où colorMode
     * et paperSize sont déjà de simples chaînes (cf. PrintJobPayload).
     */
    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $fundingSource;

    public function __construct(
        Association $association,
        int $printJobId,
        int $pageCount,
        int $copies,
        ?string $colorMode,
        ?string $paperSize,
        int $amountSpentCents,
        string $fundingSource,
    ) {
        $this->association = $association;
        $this->printJobId = $printJobId;
        $this->pageCount = $pageCount;
        $this->copies = $copies;
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->amountSpentCents = $amountSpentCents;
        $this->fundingSource = $fundingSource;
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

    public function getFundingSource(): string
    {
        return $this->fundingSource;
    }

    public function isMunicipal(): bool
    {
        return self::SOURCE_MUNICIPAL === $this->fundingSource;
    }
}
