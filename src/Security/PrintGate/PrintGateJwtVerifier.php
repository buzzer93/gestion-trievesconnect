<?php

declare(strict_types=1);

namespace App\Security\PrintGate;

use App\Entity\PrintGateDevice;
use App\Repository\PrintGateDeviceRepository;
use App\Security\PrintGate\Exception\PrintGateBodyIntegrityException;
use App\Security\PrintGate\Exception\PrintGateDeviceDisabledException;
use App\Security\PrintGate\Exception\PrintGateDeviceNotFoundException;
use App\Security\PrintGate\Exception\PrintGateJwtAlgorithmException;
use App\Security\PrintGate\Exception\PrintGateJwtClaimException;
use App\Security\PrintGate\Exception\PrintGateJwtExpiredException;
use App\Security\PrintGate\Exception\PrintGateJwtInvalidSignatureException;
use App\Security\PrintGate\Exception\PrintGateJwtMalformedException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vérifie un JWT PrintGate et l'intégrité du corps de requête associé.
 *
 * Ordre des vérifications (fail-fast, du moins coûteux au plus coûteux) :
 * 1) format JWT bien formé
 * 2) algorithme autorisé (whitelist stricte -- rejette explicitement
 *    `none` et tout algorithme symétrique type HS256)
 * 3) `sub` -> poste trouvé en base
 * 4) poste actif (`enabled`)
 * 5) signature valide avec la clé publique du poste
 * 6) `iss` / `aud` corrects, `jti` présent
 * 7) `iat` / `exp` cohérents (tolérance de clock skew configurable)
 * 8) `bodyHash` == SHA-256(corps HTTP brut)
 *
 * L'anti-rejeu (`jti`) N'EST PAS géré ici : il est appliqué par l'appelant
 * (PrintGateAuthorizeIntegrityListener) après un verify() réussi, via
 * PrintGateUsedTokenRepository::markAsUsed(), pour que l'enregistrement
 * du jti n'ait lieu qu'une fois toutes les autres vérifications passées.
 *
 * Ne jamais faire confiance à un claim lu avant l'étape 5 (signature) :
 * `peekUnverifiedClaims()` sert uniquement à retrouver le poste concerné
 * (`sub`) afin de charger la bonne clé publique -- toute donnée qui en
 * est extraite doit être revérifiée après la vérification de signature.
 */
final class PrintGateJwtVerifier
{
    /**
     * @param string[] $allowedAlgorithms
     */
    public function __construct(
        private readonly PrintGateDeviceRepository $deviceRepository,
        #[Autowire(param: 'printgate.jwt.issuer')]
        private readonly string $expectedIssuer,
        #[Autowire(param: 'printgate.jwt.audience')]
        private readonly string $expectedAudience,
        #[Autowire(param: 'printgate.jwt.allowed_algorithms')]
        private readonly array $allowedAlgorithms,
        #[Autowire(param: 'printgate.jwt.clock_skew_seconds')]
        private readonly int $clockSkewSeconds,
    ) {
    }

    /**
     * @return array{0: PrintGateDevice, 1: array<string, mixed>} le poste résolu et les claims vérifiés
     */
    public function verify(string $rawJwt, string $rawBody): array
    {
        $header = $this->peekUnverifiedHeader($rawJwt);
        $this->assertAllowedAlgorithm($header);

        $unverifiedClaims = $this->peekUnverifiedClaims($rawJwt);
        $device = $this->loadDevice((string) ($unverifiedClaims['sub'] ?? ''));
        $this->assertDeviceEnabled($device);

        $claims = $this->decodeAndVerifySignature($rawJwt, $device, (string) $header['alg']);

        $this->assertClaims($claims);
        $this->assertBodyHash($claims, $rawBody);

        return [$device, $claims];
    }

    private function peekUnverifiedHeader(string $rawJwt): array
    {
        return $this->decodeJwtSegment($rawJwt, 0);
    }

    private function peekUnverifiedClaims(string $rawJwt): array
    {
        return $this->decodeJwtSegment($rawJwt, 1);
    }

    private function decodeJwtSegment(string $rawJwt, int $segmentIndex): array
    {
        $segments = explode('.', $rawJwt);
        if (\count($segments) !== 3 || '' === $segments[$segmentIndex]) {
            throw new PrintGateJwtMalformedException('JWT mal formé (nombre de segments incorrect)');
        }

        $decoded = JWT::urlsafeB64Decode($segments[$segmentIndex]);
        $data = json_decode($decoded, true, flags: JSON_BIGINT_AS_STRING);

        if (!\is_array($data)) {
            throw new PrintGateJwtMalformedException('Segment JWT illisible (JSON invalide)');
        }

        return $data;
    }

    private function assertAllowedAlgorithm(array $header): void
    {
        $alg = $header['alg'] ?? null;

        if (!\is_string($alg) || !\in_array($alg, $this->allowedAlgorithms, true)) {
            throw new PrintGateJwtAlgorithmException(\sprintf('Algorithme non autorisé : %s', $alg ?? 'absent'));
        }
    }

    private function loadDevice(string $sub): PrintGateDevice
    {
        if ('' === $sub) {
            throw new PrintGateDeviceNotFoundException('Claim "sub" absent ou vide');
        }

        $device = $this->deviceRepository->findOneByComputerId($sub);

        if (null === $device) {
            throw new PrintGateDeviceNotFoundException(\sprintf('Poste inconnu : %s', $sub));
        }

        return $device;
    }

    private function assertDeviceEnabled(PrintGateDevice $device): void
    {
        if (!$device->isEnabled()) {
            throw new PrintGateDeviceDisabledException(\sprintf('Poste désactivé : %s', $device->getComputerId()));
        }
    }

    private function decodeAndVerifySignature(string $rawJwt, PrintGateDevice $device, string $alg): array
    {
        $publicKey = $device->getPublicKey();

        if (null === $publicKey || '' === trim($publicKey)) {
            // Poste enregistré mais jamais enrôlé avec une clé publique :
            // traité comme une signature invalide, pas comme un poste
            // inconnu (le poste existe bel et bien en base).
            throw new PrintGateJwtInvalidSignatureException(
                \sprintf('Aucune clé publique enregistrée pour le poste %s', $device->getComputerId()),
            );
        }

        // firebase/php-jwt >= 7.0.4 attend la clé EdDSA encodée en base64url
        // (cf. "use urlsafeB64Decode everywhere"), pas les octets bruts.
        $keyMaterial = 'EdDSA' === $alg
            ? JWT::urlsafeB64Encode($this->extractRawEd25519PublicKey($publicKey))
            : $publicKey;

        // Laisse firebase/php-jwt appliquer LA MÊME tolérance de clock skew
        // que celle configurée pour assertClaims() ci-dessous -- sans quoi
        // decode() utiliserait son leeway par défaut (0), plus strict que
        // ce que le projet a choisi de tolérer.
        JWT::$leeway = $this->clockSkewSeconds;

        try {
            $decoded = JWT::decode($rawJwt, new Key($keyMaterial, $alg));
        } catch (ExpiredException|BeforeValidException $exception) {
            // exp dépassé / iat-nbf dans le futur : ne pas confondre avec
            // une signature invalide (cf. testRejectsExpiredToken).
            throw new PrintGateJwtExpiredException(
                \sprintf('JWT expiré ou non encore valide pour le poste %s', $device->getComputerId()),
                previous: $exception,
            );
        } catch (SignatureInvalidException $exception) {
            throw new PrintGateJwtInvalidSignatureException(
                \sprintf('Signature JWT invalide pour le poste %s', $device->getComputerId()),
                previous: $exception,
            );
        } catch (\Throwable $exception) {
            // Format/domain errors (JSON illisible, claims mal typés, etc.) :
            // traités prudemment comme un échec de signature plutôt que
            // laissés remonter tels quels.
            throw new PrintGateJwtInvalidSignatureException(
                \sprintf('JWT illisible pour le poste %s : %s', $device->getComputerId(), $exception->getMessage()),
                previous: $exception,
            );
        }

        return (array) $decoded;
    }

    /**
     * Extrait la clé publique brute (32 octets) d'une clé Ed25519 au
     * format PEM (SubjectPublicKeyInfo) produite par
     * `openssl genpkey -algorithm ed25519`. La bibliothèque firebase/php-jwt
     * attend la clé brute pour EdDSA (support basé sur libsodium), pas le
     * PEM tel quel -- contrairement à RS256, où le PEM est utilisé directement.
     */
    private function extractRawEd25519PublicKey(string $pem): string
    {
        $lines = preg_split('/\r?\n/', trim($pem)) ?: [];
        $base64 = implode('', array_filter(
            $lines,
            static fn (string $line): bool => !str_starts_with(trim($line), '-----'),
        ));

        $der = base64_decode($base64, true);

        if (false === $der || \strlen($der) < 32) {
            throw new PrintGateJwtInvalidSignatureException('Clé publique Ed25519 illisible (PEM invalide)');
        }

        // SubjectPublicKeyInfo Ed25519 : préfixe ASN.1 fixe de 12 octets
        // suivi des 32 octets de clé brute -- cf. RFC 8410.
        return substr($der, -32);
    }

    private function assertClaims(array $claims): void
    {
        if (($claims['iss'] ?? null) !== $this->expectedIssuer) {
            throw new PrintGateJwtClaimException('Claim "iss" incorrect');
        }

        if (($claims['aud'] ?? null) !== $this->expectedAudience) {
            throw new PrintGateJwtClaimException('Claim "aud" incorrect');
        }

        if (empty($claims['jti'])) {
            throw new PrintGateJwtClaimException('Claim "jti" absent ou vide');
        }

        $now = time();
        $iat = (int) ($claims['iat'] ?? 0);
        $exp = (int) ($claims['exp'] ?? 0);

        if ($iat > $now + $this->clockSkewSeconds) {
            throw new PrintGateJwtExpiredException('Claim "iat" situé dans le futur au-delà de la tolérance');
        }

        if ($exp < $now - $this->clockSkewSeconds) {
            throw new PrintGateJwtExpiredException('JWT expiré');
        }
    }

    private function assertBodyHash(array $claims, string $rawBody): void
    {
        $claimedHash = $claims['bodyHash'] ?? null;

        if (!\is_string($claimedHash) || '' === $claimedHash) {
            throw new PrintGateBodyIntegrityException('Claim "bodyHash" absent');
        }

        $computedHash = JWT::urlsafeB64Encode(hash('sha256', $rawBody, binary: true));

        if (!hash_equals($computedHash, $claimedHash)) {
            throw new PrintGateBodyIntegrityException('bodyHash ne correspond pas au corps reçu');
        }
    }
}
