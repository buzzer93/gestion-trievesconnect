<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintPriceRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tarif d'impression pour une combinaison couleur/format, dans une
 * "portée" donnée (cf. $scope) -- remplace la grille unique partagée
 * client/association par 3 grilles distinctes (décision du 2026-08-25) :
 * - CLIENT : clients classiques ;
 * - ASSOCIATION : associations, financement personnel ;
 * - MUNICIPAL : associations, financement mairie -- prix propre (souvent
 *   inférieur au tarif ASSOCIATION) ET $enabled qui sert de règle
 *   d'éligibilité au financement municipal : une combinaison n'est
 *   utilisable par la mairie que si une ligne MUNICIPAL existe pour elle
 *   ET est activée. Pas de règle "A4 + N&B" codée en dur ailleurs dans le
 *   projet -- l'éligibilité est entièrement pilotée par cette donnée,
 *   modifiable depuis l'admin sans déploiement.
 */
#[ORM\Entity(repositoryClass: PrintPriceRateRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRICE_RATE_SCOPE_TYPE', columns: ['scope', 'color_mode', 'paper_size'])]
class PrintPriceRate
{
    public const SCOPE_CLIENT = 'CLIENT';
    public const SCOPE_ASSOCIATION = 'ASSOCIATION';
    public const SCOPE_MUNICIPAL = 'MUNICIPAL';
    public const SCOPES = [self::SCOPE_CLIENT, self::SCOPE_ASSOCIATION, self::SCOPE_MUNICIPAL];

    /**
     * Mode réservé aux services hors impression (plastification, reliure...,
     * demande client du 2026-11-25) : $paperSize porte alors le code du
     * service. Réutiliser la grille plutôt qu'un modèle "produit" dédié
     * laisse PrintPolicyEvaluator inchangé (priorité mairie, historique,
     * annulation, facture trimestrielle). PrintGate ne peut jamais débiter
     * un service : PrintJobPayload n'accepte que COLOR/MONOCHROME.
     */
    public const COLOR_MODE_SERVICE = 'SERVICE';
    public const COLOR_MODES = ['MONOCHROME', 'COLOR', self::COLOR_MODE_SERVICE];

    private const COLOR_MODE_LABELS = ['MONOCHROME' => 'Monochrome', 'COLOR' => 'Couleur'];
    private const SERVICE_LABELS = ['PLASTIFICATION' => 'Plastification', 'RELIURE_DOSSIER' => 'Reliure de dossier'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $scope;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $colorMode;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $paperSize;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priceCents;

    /**
     * Pour scope=MUNICIPAL : la mairie finance-t-elle réellement cette
     * combinaison couleur/format ? (cf. PHPDoc de classe). Pour
     * scope=CLIENT/ASSOCIATION : permet de désactiver temporairement une
     * combinaison sans la supprimer (même sémantique "pas de tarif
     * applicable" que $enabled=false côté mairie).
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled;

    public function __construct(string $scope, string $colorMode, string $paperSize, int $priceCents, bool $enabled = true)
    {
        $this->scope = $scope;
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->priceCents = $priceCents;
        $this->enabled = $enabled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getColorMode(): string
    {
        return $this->colorMode;
    }

    public function getPaperSize(): string
    {
        return $this->paperSize;
    }

    public function isService(): bool
    {
        return self::COLOR_MODE_SERVICE === $this->colorMode;
    }

    public function getLabel(): string
    {
        return self::labelFor($this->colorMode, $this->paperSize);
    }

    /**
     * Libellé lisible d'un article ("Monochrome A4", "Plastification"),
     * aussi utilisé pour les transactions, qui stockent couleur/format en
     * chaînes. Valeurs nulles = anciennes impressions, considérées
     * MONOCHROME/A4 comme ailleurs dans le projet.
     */
    public static function labelFor(?string $colorMode, ?string $paperSize): string
    {
        $colorMode ??= 'MONOCHROME';
        $paperSize ??= 'A4';

        if (self::COLOR_MODE_SERVICE === $colorMode) {
            return self::SERVICE_LABELS[$paperSize] ?? ucfirst(strtolower(str_replace('_', ' ', $paperSize)));
        }

        return (self::COLOR_MODE_LABELS[$colorMode] ?? $colorMode).' '.$paperSize;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): self
    {
        $this->priceCents = max(0, $priceCents);

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
