<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Picqer\Barcode\BarcodeGeneratorPNG;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $barCode = null;

    #[ORM\Column(nullable: true)]
    private ?float $sellingPrice = null;

    #[ORM\Column(nullable: true)]
    private ?float $marginAmount = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $purchasePrice = 0;

    public function __construct()
    {
        $this->purchasePrice = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
    public function getLabelingName(): ?string
    {
        // Sépare la chaîne en mots
        $mots = explode(' ', $this->name);

        // Vérifie si la chaîne contient 1 ou 2 mots
        if (count($mots) <= 2) {
            return $this->name;
        }

        // Sinon Retourne les deux premiers mots sur la première ligne et les deux suivants sur la deuxième ligne le reste n'est pas retourné
        return $mots[0] . ' ' . $mots[1] . '<br>' . $mots[2] . ' ' . $mots[3];
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getBarCode(): ?string
    {
        return $this->barCode;
    }
    public function getBarCodeImage(): string
    {
        $barcodeGenerator = new BarcodeGeneratorPNG();
        return 'data:image/png;base64,' . base64_encode($barcodeGenerator->getBarcode($this->barCode, $barcodeGenerator::TYPE_CODE_128));
    }

    public function setBarCode(string $barCode): static
    {
        $this->barCode = $barCode;

        return $this;
    }

    public function getSellingPrice(): ?float
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(?float $sellingPrice): static
    {
        $this->sellingPrice = $sellingPrice;
        $this->updateMargin();
        return $this;
    }
    public function getSellingPriceHT(): ?float
    {
        return $this->sellingPrice / 1.2;
    }

    public function getMarginAmount(): ?float
    {
        return $this->marginAmount;
    }

    public function setMarginAmount(?float $marginAmount): static
    {
        $this->marginAmount = $marginAmount;

        return $this;
    }
    public function updateMargin(): static
    {
        $this->setMarginAmount(round(($this->sellingPrice / 1.2), 2));

        return $this;
    }

    public function getPurchasePrice(): ?int
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(int $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }
    public function getType(): string
    {
        return 'service';
    }
}
