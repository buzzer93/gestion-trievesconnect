<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Picqer\Barcode\BarcodeGeneratorPNG;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    private string $phoneNumber;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column]
    private int $credits = 0;

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

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getCredits(): int
    {
        return $this->credits;
    }

    public function addCredits(int $amount): self
    {
        if ($amount > 0) {
            $this->credits += $amount;
        }
        return $this;
    }

    public function removeCredits(int $amount): self
    {
        if ($amount <= 0) {
            return $this;
        }
        if ($this->credits - $amount < 0) {
            trigger_error("Crédits insuffisants", E_USER_WARNING);
            return $this;
        }
        $this->credits -= $amount;
        return $this;
    }

    public function setCredits(int $credits): self
    {
        $this->credits = max(0, $credits);
        return $this;
    }

    /**
     * Alias sémantique : le solde est stocké en centimes pour éviter les float.
     */
    public function getBalanceCents(): int
    {
        return $this->credits;
    }

    /**
     * Retourne une représentation en euros (formatée) pour affichage.
     */
    public function getBalanceEuros(): string
    {
        return number_format($this->credits / 100, 2, ',', '');
    }

    public function setBalanceCents(int $cents): self
    {
        return $this->setCredits(max(0, $cents));
    }

    public function addBalanceCents(int $cents): self
    {
        return $this->addCredits(max(0, $cents));
    }

    public function removeBalanceCents(int $cents): self
    {
        return $this->removeCredits(max(0, $cents));
    }

    public function getBarCodeImage(): string
    {
        $value = $this->phoneNumber ?? '';
        if ($value === '') {
            return '';
        }
        $generator = new BarcodeGeneratorPNG();
        // Utiliser CODE_128 pour cohérence avec Product & Service
        $pngData = $generator->getBarcode($value, $generator::TYPE_CODE_128);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }
}
