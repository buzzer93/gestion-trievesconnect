<?php

declare(strict_types=1);

namespace App\Tests\Security\PrintGate;

use App\Entity\PrintGateDevice;
use App\Repository\PrintGateDeviceRepository;
use App\Security\PrintGate\Exception\PrintGateBodyIntegrityException;
use App\Security\PrintGate\Exception\PrintGateDeviceDisabledException;
use App\Security\PrintGate\Exception\PrintGateDeviceNotFoundException;
use App\Security\PrintGate\Exception\PrintGateJwtAlgorithmException;
use App\Security\PrintGate\Exception\PrintGateJwtClaimException;
use App\Security\PrintGate\Exception\PrintGateJwtExpiredException;
use App\Security\PrintGate\Exception\PrintGateJwtInvalidSignatureException;
use App\Security\PrintGate\PrintGateJwtVerifier;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

/**
 * Nécessite l'extension sodium (incluse par défaut depuis PHP 7.2) pour
 * générer des paires de clés Ed25519 de test sans dépendre d'openssl en
 * ligne de commande.
 */
final class PrintGateJwtVerifierTest extends TestCase
{
    private const ISSUER = 'printgate-agent';
    private const AUDIENCE = 'gestion.trievesconnect.fr';
    private const COMPUTER_ID = 'POSTE-LINUX-01';

    public function testAcceptsValidRequestEndToEnd(): void
    {
        [$device, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{"identifier":"j.dupont"}';
        $jwt = $this->signJwt($secretKey, $body);

        [$resolvedDevice, $claims] = $verifier->verify($jwt, $body);

        self::assertSame($device, $resolvedDevice);
        self::assertSame(self::COMPUTER_ID, $claims['sub']);
    }

    public function testRejectsInvalidSignature(): void
    {
        [, $verifier] = $this->buildVerifierWithDevice();

        $otherKeyPair = sodium_crypto_sign_keypair();
        $otherSecretKey = sodium_crypto_sign_secretkey($otherKeyPair);
        $body = '{}';
        $jwt = $this->signJwt($otherSecretKey, $body);

        $this->expectException(PrintGateJwtInvalidSignatureException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsExpiredToken(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{}';
        $jwt = $this->signJwt($secretKey, $body, [
            'iat' => time() - 120,
            'exp' => time() - 60,
        ]);

        $this->expectException(PrintGateJwtExpiredException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsTokenIssuedInFuture(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{}';
        $jwt = $this->signJwt($secretKey, $body, [
            'iat' => time() + 3600,
            'exp' => time() + 3660,
        ]);

        $this->expectException(PrintGateJwtExpiredException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsInvalidBodyHash(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $signedBody = '{"pageCount":4}';
        $jwt = $this->signJwt($secretKey, $signedBody);

        // Le corps réellement reçu diffère d'un seul caractère du corps signé.
        $tamperedBody = '{"pageCount":5}';

        $this->expectException(PrintGateBodyIntegrityException::class);
        $verifier->verify($jwt, $tamperedBody);
    }

    public function testRejectsDisabledDevice(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice(enabled: false);
        $body = '{}';
        $jwt = $this->signJwt($secretKey, $body);

        $this->expectException(PrintGateDeviceDisabledException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsUnknownDevice(): void
    {
        $verifier = new PrintGateJwtVerifier(
            $this->deviceRepositoryReturning(null),
            self::ISSUER,
            self::AUDIENCE,
            ['EdDSA', 'RS256'],
            5,
        );

        $keyPair = sodium_crypto_sign_keypair();
        $body = '{}';
        $jwt = $this->signJwt(sodium_crypto_sign_secretkey($keyPair), $body);

        $this->expectException(PrintGateDeviceNotFoundException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsUnauthorizedAlgorithm(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{}';

        // HS256 (symétrique) n'a pas de sens dans ce modèle et doit être
        // rejeté avant même toute tentative de vérification de signature.
        $jwt = JWT::encode([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => self::COMPUTER_ID,
            'jti' => 'x',
            'iat' => time(),
            'exp' => time() + 60,
            'bodyHash' => JWT::urlsafeB64Encode(hash('sha256', $body, true)),
        ], 'une-cle-symetrique-quelconque-assez-longue-pour-hs256', 'HS256');

        $this->expectException(PrintGateJwtAlgorithmException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsWrongIssuer(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{}';
        $jwt = $this->signJwt($secretKey, $body, ['iss' => 'un-autre-emetteur']);

        $this->expectException(PrintGateJwtClaimException::class);
        $verifier->verify($jwt, $body);
    }

    public function testRejectsWrongAudience(): void
    {
        [, $verifier, $secretKey] = $this->buildVerifierWithDevice();
        $body = '{}';
        $jwt = $this->signJwt($secretKey, $body, ['aud' => 'un-autre-site.fr']);

        $this->expectException(PrintGateJwtClaimException::class);
        $verifier->verify($jwt, $body);
    }

    /**
     * @return array{0: PrintGateDevice, 1: PrintGateJwtVerifier, 2: string}
     */
    private function buildVerifierWithDevice(bool $enabled = true): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKeyRaw = sodium_crypto_sign_publickey($keyPair);

        $device = new PrintGateDevice(self::COMPUTER_ID, 'poste-linux-01');
        $device->setPublicKey($this->rawEd25519PublicKeyToPem($publicKeyRaw));
        $device->setEnabled($enabled);

        $verifier = new PrintGateJwtVerifier(
            $this->deviceRepositoryReturning($device),
            self::ISSUER,
            self::AUDIENCE,
            ['EdDSA', 'RS256'],
            5,
        );

        return [$device, $verifier, $secretKey];
    }

    private function deviceRepositoryReturning(?PrintGateDevice $device): PrintGateDeviceRepository
    {
        $repository = $this->createMock(PrintGateDeviceRepository::class);
        $repository->method('findOneByComputerId')->willReturn($device);

        return $repository;
    }

    private function signJwt(string $secretKey, string $body, array $claimOverrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => self::COMPUTER_ID,
            'jti' => bin2hex(random_bytes(8)),
            'iat' => $now,
            'exp' => $now + 60,
            'bodyHash' => JWT::urlsafeB64Encode(hash('sha256', $body, true)),
        ], $claimOverrides);

        return JWT::encode($claims, JWT::urlsafeB64Encode($secretKey), 'EdDSA');
    }

    /**
     * Reconstruit un PEM SubjectPublicKeyInfo Ed25519 (RFC 8410) à partir
     * de la clé publique brute de 32 octets, pour reproduire fidèlement
     * le format produit par `openssl genpkey -algorithm ed25519` et ainsi
     * tester le même chemin de code que PrintGateJwtVerifier::extractRawEd25519PublicKey().
     */
    private function rawEd25519PublicKeyToPem(string $raw32Bytes): string
    {
        $asn1Prefix = hex2bin('302a300506032b6570032100');
        $der = $asn1Prefix.$raw32Bytes;
        $base64 = chunk_split(base64_encode($der), 64, "\n");

        return "-----BEGIN PUBLIC KEY-----\n{$base64}-----END PUBLIC KEY-----\n";
    }
}
