<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Picqer\Barcode\BarcodeGeneratorDynamicHTML;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\Exceptions\UnknownTypeException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Constraint;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[UniqueEntity('name')]
#[UniqueEntity('barCode')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Constraint\Length(min: 5)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Constraint\Length(min: 5)]
    #[Constraint\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Invalid slug')]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $barCode = null;

    #[ORM\Column(length: 255)]
    private ?string $comment = '';

    #[ORM\Column]
    private ?float $marginRate = null;

    #[ORM\Column]
    private ?float $marginAmount = null;


    #[ORM\Column(length: 255)]
    private ?string $supplier = '';

    #[ORM\Column]
    private int $stock = 0;

    #[ORM\Column]
    private ?float $purchasePrice = null;


    #[ORM\Column]
    private ?float $sellingPrice = null;


    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'products')]
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
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
        return "{$mots[0]} {$mots[1]}<br>" . (isset($mots[2]) ? "{$mots[2]} " : '') . (isset($mots[3]) ? $mots[3] : '');
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getPurchasePrice(): ?float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;
        $this->updateMargin();

        return $this;
    }


    public function getSellingPrice(): ?float
    {
        return $this->sellingPrice;
    }
    public function getSellingPriceHT(): ?float
    {
        return $this->sellingPrice / 1.2;
    }

    public function setSellingPrice(float $sellingPrice): static
    {
        $this->sellingPrice = $sellingPrice;
        $this->updateMargin();
        return $this;
    }

    /**
     * Get the value of supplier
     *
     * @return ?string
     */
    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    /**
     * Set the value of supplier
     *
     * @param ?string $supplier
     *
     * @return self
     */
    public function setSupplier(?string $supplier): self
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * Get the value of stock
     *
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    /**
     * Set the value of stock
     *
     * @param int $stock
     *
     * @return self
     */
    public function setStock(int $stock): self
    {
        $this->stock = $stock;

        return $this;
    }

    /**
     * Get the value of comment
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Set the value of comment
     *
     * @param string $comment
     *
     * @return self
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }
    public function getCategoriesName(): string
    {
        $categoriesNames = [];
        foreach ($this->categories as $category) {
            $categoriesNames[] = $category->getName();
        }
        return implode(', ', $categoriesNames);
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addProduct($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeProduct($this);
        }
        return $this;
    }

    /**
     * Get the value of marginRate
     *
     * @return ?float
     */
    public function getMarginRate(): ?float
    {
        return $this->marginRate;
    }

    /**
     * Set the value of marginRate
     *
     * @param ?float $marginRate
     *
     * @return self
     */
    public function setMarginRate(?float $marginRate): self
    {
        $this->marginRate = $marginRate;

        return $this;
    }

    /**
     * Get the value of marginAmount
     *
     * @return ?float
     */
    public function getMarginAmount(): ?float
    {
        return $this->marginAmount;
    }

    /**
     * Set the value of marginAmount
     *
     * @param ?float $marginAmount
     *
     * @return self
     */
    public function setMarginAmount(?float $marginAmount): self
    {
        $this->marginAmount = $marginAmount;

        return $this;
    }

    public function updateMargin(): static
    {
        $this->setMarginAmount(round(($this->sellingPrice / 1.2) - $this->purchasePrice, 2));
        $this->setMarginRate(round((($this->sellingPrice / 1.2) / $this->purchasePrice -1 ) * 100, 2));
        return $this;
    }
    public function getType(): string
    {
        return 'product';
    }
    /**
     * Marge % par rapport au prix de vente HT
     * (= ((PV HT – PA) / PV HT) × 100 )
     */
    public function getMarkRate(): ?float // taux de marque
{
    if ($this->sellingPrice === null || $this->purchasePrice === null) {
        return null;
    }
    $pvht = $this->sellingPrice / 1.2;
    if ($pvht == 0) {
        return null;
    }
    return round((($pvht - $this->purchasePrice) / $pvht) * 100, 2);
}
}
