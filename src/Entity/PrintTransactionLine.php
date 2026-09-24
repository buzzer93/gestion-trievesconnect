<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintTransactionLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part d'une PrintTransaction financée par une source donnée, à un tarif
 * donné -- une transaction associative éligible au financement mairie
 * produit jusqu'à 2 lignes (MUNICIPAL puis ASSOCIATION_PERSONAL) quand le
 * crédit mairie ne couvre pas la totalité des copies (bascule par unité,
 * cf. PrintPolicyEvaluator). $unitPriceCents est dupliqué ici plutôt que
 * recalculé depuis PrintPriceRate : la grille peut changer après coup,
 * l'historique doit rester fidèle au tarif réellement appliqué au moment
 * du débit.
 */
#[ORM\Entity(repositoryClass: PrintTransactionLineRepository::class)]
class PrintTransactionLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PrintTransaction::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private PrintTransaction $transaction;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $fundingSource;

    #[ORM\Column(type: Types::INTEGER)]
    private int $copies;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unitPriceCents;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amountCents;

    public function __construct(string $fundingSource, int $copies, int $unitPriceCents)
    {
        $this->fundingSource = $fundingSource;
        $this->copies = $copies;
        $this->unitPriceCents = $unitPriceCents;
        $this->amountCents = $copies * $unitPriceCents;
    }

    public function setTransaction(PrintTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransaction(): PrintTransaction
    {
        return $this->transaction;
    }

    public function getFundingSource(): string
    {
        return $this->fundingSource;
    }

    public function getCopies(): int
    {
        return $this->copies;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }
}
