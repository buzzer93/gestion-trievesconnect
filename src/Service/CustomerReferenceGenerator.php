<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CustomerRepository;

/**
 * Génère la référence unique d'un client/association (cf. Customer::$reference)
 * -- remplace le téléphone comme identifiant de code-barres et de recherche
 * PrintGate (décision du 2026-09-10).
 *
 * Format : préfixe fixe "7777" + 5 chiffres aléatoires + 1 chiffre de
 * contrôle Luhn, soit 10 chiffres au total (même longueur qu'un numéro de
 * téléphone). Volontairement ALÉATOIRE et non séquentiel : un identifiant
 * séquentiel (7777000001, 7777000002...) serait aussi facile à deviner
 * qu'un id de base -- il suffit d'essayer les entiers dans l'ordre. Le
 * chiffre de contrôle rejette côté serveur la quasi-totalité des saisies
 * au hasard sans même interroger la base, et rattrape au passage les
 * fautes de frappe (transposition de deux chiffres).
 */
class CustomerReferenceGenerator
{
    private const PREFIX = '7777';
    private const RANDOM_DIGITS = 5;

    public function __construct(private readonly CustomerRepository $customerRepository)
    {
    }

    /**
     * Génère une référence garantie unique en base (vérifiée à chaque
     * tentative -- collision extrêmement improbable sur 100 000
     * combinaisons pour le nombre de comptes de ce projet, mais on ne
     * suppose jamais l'unicité sans la vérifier).
     */
    public function generate(): string
    {
        do {
            $candidate = $this->generateCandidate();
        } while (null !== $this->customerRepository->findOneByReference($candidate));

        return $candidate;
    }

    /**
     * Vérifie qu'une référence est bien formée (préfixe + longueur + chiffre
     * de contrôle valide), sans interroger la base. Utile pour rejeter
     * immédiatement une saisie manuelle erronée.
     */
    public static function isValid(string $reference): bool
    {
        if (1 !== preg_match('/^' . self::PREFIX . '\d{' . (self::RANDOM_DIGITS + 1) . '}$/', $reference)) {
            return false;
        }

        $payload = substr($reference, 0, -1);
        $checkDigit = (int) substr($reference, -1);

        return self::luhnCheckDigit($payload) === $checkDigit;
    }

    private function generateCandidate(): string
    {
        $random = '';
        for ($i = 0; $i < self::RANDOM_DIGITS; $i++) {
            $random .= random_int(0, 9);
        }
        $payload = self::PREFIX . $random;

        return $payload . self::luhnCheckDigit($payload);
    }

    private static function luhnCheckDigit(string $payload): int
    {
        $sum = 0;
        $alternate = true;
        for ($i = strlen($payload) - 1; $i >= 0; $i--) {
            $digit = (int) $payload[$i];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
