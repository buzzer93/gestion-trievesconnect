<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintTransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une transaction = une décision d'impression autorisée (client classique
 * ou association), qu'elle vienne de PrintGate (poste + jobId renseignés)
 * ou d'un débit manuel depuis le back-office (les deux nullables).
 *
 * Remplace PrintMunicipalConsumption (conservée telle quelle comme
 * archive historique, non modifiée par cette migration -- cf. PHPDoc de
 * Version20260825170000) : couvre désormais aussi les clients classiques
 * (comblant le manque d'historisation signalé lors de l'analyse), et porte
 * la clé d'idempotence.
 *
 * L'idempotence PrintGate repose sur la contrainte UNIQUE
 * (print_gate_device_id, job_id) : deux demandes avec le même couple ne
 * peuvent jamais produire deux lignes -- c'est elle, pas une simple
 * vérification applicative, qui protège contre le double débit sous
 * concurrence (cf. PrintTransactionRepository::recordCharge()). Les
 * transactions sans poste (débit manuel admin) ont job_id/poste à NULL ;
 * NULL n'entre jamais en conflit avec NULL dans une contrainte UNIQUE
 * (MySQL comme SQLite), donc aucune limite sur le nombre de débits
 * manuels.
 *
 * Une impression peut être financée par 2 sources différentes à des tarifs
 * différents (bascule par unité mairie -> personnel, cf.
 * PrintPolicyEvaluator) : le détail par source vit dans les
 * PrintTransactionLine enfants, pas ici.
 */
#[ORM\Entity(repositoryClass: PrintTransactionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRINT_TX_DEVICE_JOB', columns: ['print_gate_device_id', 'job_id'])]
class PrintTransaction
{
    public const FUNDING_CUSTOMER = 'CUSTOMER';
    public const FUNDING_ASSOCIATION_PERSONAL = 'ASSOCIATION_PERSONAL';
    public const FUNDING_MUNICIPAL = 'MUNICIPAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Référence publique (ULID), renvoyée à PrintGate et affichée en
     * admin -- volontairement distincte de l'id auto-incrémenté interne.
     */
    #[ORM\Column(type: Types::STRING, length: 26, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    /**
     * onDelete SET NULL : la suppression d'un poste PrintGate ne doit
     * jamais entraîner la perte de l'historique financier -- seule la
     * référence au poste est perdue, la transaction reste (cf. règle
     * projet : conserver une trace des mouvements).
     */
    #[ORM\ManyToOne(targetEntity: PrintGateDevice::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PrintGateDevice $printGateDevice;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $jobId;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $colorMode;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $paperSize;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pageCount;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $duplexMode;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $motif;

    /**
     * @var Collection<int, PrintTransactionLine>
     */
    #[ORM\OneToMany(targetEntity: PrintTransactionLine::class, mappedBy: 'transaction', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct(
        Customer $customer,
        ?PrintGateDevice $printGateDevice,
        ?int $jobId,
        ?string $colorMode,
        ?string $paperSize,
        int $pageCount,
        ?string $duplexMode,
        ?User $createdBy = null,
        ?string $motif = null,
    ) {
        $this->reference = self::generateReference();
        $this->customer = $customer;
        $this->printGateDevice = $printGateDevice;
        $this->jobId = $jobId;
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->pageCount = $pageCount;
        $this->duplexMode = $duplexMode;
        $this->createdBy = $createdBy;
        $this->motif = $motif;
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function addLine(PrintTransactionLine $line): self
    {
        $this->lines->add($line);
        $line->setTransaction($this);

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getPrintGateDevice(): ?PrintGateDevice
    {
        return $this->printGateDevice;
    }

    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    public function getColorMode(): ?string
    {
        return $this->colorMode;
    }

    public function getPaperSize(): ?string
    {
        return $this->paperSize;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function getDuplexMode(): ?string
    {
        return $this->duplexMode;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    /**
     * @return Collection<int, PrintTransactionLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function getTotalAmountCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getAmountCents();
        }

        return $total;
    }

    public function getTotalCopies(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getCopies();
        }

        return $total;
    }

    /**
     * "MIXED" si plus d'une source a été sollicitée (bascule mairie ->
     * personnel) -- l'agent PrintGate n'a pas besoin du détail par ligne,
     * juste de savoir si le financement était homogène ou pas.
     */
    public function getFundingSourceSummary(): string
    {
        $sources = [];
        foreach ($this->lines as $line) {
            $sources[$line->getFundingSource()] = true;
        }

        if (1 === count($sources)) {
            return array_key_first($sources);
        }

        return 'MIXED';
    }

    private static function generateReference(): string
    {
        // Pas de dépendance à un générateur d'ULID externe : suffisamment
        // unique pour une référence de transaction (horodatage micro +
        // aléa), pas un identifiant cryptographique.
        return strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(6)));
    }
}
