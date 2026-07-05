<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\PrintGate;

use App\Entity\Customer;
use App\Entity\PrintGateDevice;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Étape 5 : ces tests remplacent ceux de l'étape 4. La vérification JWT
 * a désormais lieu sur kernel.request (PrintGateAuthorizeIntegrityListener),
 * AVANT la résolution des arguments du contrôleur -- toute requête doit
 * donc porter un JWT valide, y compris pour tester la validation du
 * payload (422) ou l'intégrité du corps (400), sans quoi elle échoue à
 * 401 avant même d'atteindre ces vérifications.
 */
final class PrintAuthorizationControllerTest extends WebTestCase
{
    private const COMPUTER_ID = 'TEST-POSTE-CI';
    private const ISSUER = 'printgate-agent';
    private const AUDIENCE = 'gestion.trievesconnect.fr';
    private const CUSTOMER_PHONE_NUMBER = '0611223344';

    public function testNominalRequestReturnsAuthorizationDecision(): void
    {
        $client = static::createClient();
        $secretKey = $this->registerTestDevice();
        $this->registerTestCustomer();
        $body = $this->samplePayload();

        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->signJwt($secretKey, $body),
        ], content: $body);

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        // PrintPolicyEvaluator autorise si un Customer avec ce numéro de
        // téléphone existe et a assez de crédits -- cf. registerTestCustomer().
        self::assertTrue($payload['authorizedImpression']);
        self::assertNull($payload['reason'] ?? null);
    }

    public function testAccessWithoutJwtIsRejected(): void
    {
        $client = static::createClient();
        $this->registerTestDevice();

        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: $this->samplePayload());

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidPayloadReturnsUnprocessableEntity(): void
    {
        $client = static::createClient();
        $secretKey = $this->registerTestDevice();

        $body = json_encode([
            'identifier' => '',
            'computerId' => self::COMPUTER_ID,
            'hostname' => 'poste-ci',
            'printJob' => ['jobId' => 1, 'printerName' => 'x', 'documentName' => 'y'],
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->signJwt($secretKey, $body),
        ], content: $body);

        // Comportement réel constaté à l'étape 4 : #[MapRequestPayload]
        // renvoie 422 sur un payload invalide, pas 400.
        self::assertResponseStatusCodeSame(422);
    }

    public function testWrongHttpMethodReturnsMethodNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/printgate/authorize');

        self::assertResponseStatusCodeSame(405);
    }

    public function testTamperedBodyIsRejected(): void
    {
        $client = static::createClient();
        $secretKey = $this->registerTestDevice();

        $signedBody = $this->samplePayload();
        $jwt = $this->signJwt($secretKey, $signedBody);

        // Le corps réellement envoyé diffère du corps signé : le bodyHash
        // du JWT ne correspondra plus aux octets reçus.
        $tamperedBody = str_replace('"copies":1', '"copies":9', $signedBody);

        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ], content: $tamperedBody);

        self::assertResponseStatusCodeSame(400);
    }

    public function testReplayedJtiIsRejected(): void
    {
        $client = static::createClient();
        $secretKey = $this->registerTestDevice();
        $this->registerTestCustomer();
        $body = $this->samplePayload();
        $jwt = $this->signJwt($secretKey, $body);

        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ], content: $body);
        self::assertResponseIsSuccessful();

        // Même JWT, même corps, rejoué une seconde fois.
        $client->request('POST', '/api/printgate/authorize', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ], content: $body);
        self::assertResponseStatusCodeSame(409);
    }

    private function samplePayload(): string
    {
        return json_encode([
            'identifier' => self::CUSTOMER_PHONE_NUMBER,
            'computerId' => self::COMPUTER_ID,
            'hostname' => 'poste-ci',
            'printJob' => [
                'jobId' => 42,
                'printerName' => 'Imprimante-WiFi',
                'documentName' => 'document.pdf',
                'pageCount' => 4,
                'copies' => 1,
                'paperSize' => 'A4',
                'colorMode' => 'COLOR',
                'duplexMode' => 'ONE_SIDED',
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Enregistre (ou réenregistre) un PrintGateDevice de test et retourne
     * sa clé privée Ed25519 brute (format sodium), pour signer des JWT
     * dans les tests.
     */
    private function registerTestDevice(): string
    {
        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKeyRaw = sodium_crypto_sign_publickey($keyPair);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(PrintGateDevice::class)
            ->findOneBy(['computerId' => self::COMPUTER_ID]);

        if (null !== $existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $device = new PrintGateDevice(self::COMPUTER_ID, 'poste-ci');
        $device->setPublicKey($this->rawEd25519PublicKeyToPem($publicKeyRaw));
        $entityManager->persist($device);
        $entityManager->flush();

        return $secretKey;
    }

    /**
     * Enregistre (ou réenregistre) le Customer attendu par samplePayload()
     * (identifier = numéro de téléphone, même identifiant que la carte
     * client), avec assez de crédits pour que PrintPolicyEvaluator
     * autorise l'impression (COLOR/A4 x1 copie = 50c, largement couvert
     * par 10000c).
     */
    private function registerTestCustomer(int $balanceCents = 10000): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(Customer::class)
            ->findOneBy(['phoneNumber' => self::CUSTOMER_PHONE_NUMBER]);

        if (null !== $existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $customer = (new Customer())
            ->setName('J. Dupont')
            ->setPhoneNumber(self::CUSTOMER_PHONE_NUMBER)
            ->setBalanceCents($balanceCents);

        $entityManager->persist($customer);
        $entityManager->flush();
    }

    private function signJwt(string $secretKey, string $body): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => self::COMPUTER_ID,
            'jti' => bin2hex(random_bytes(8)),
            'iat' => $now,
            'exp' => $now + 60,
            'bodyHash' => JWT::urlsafeB64Encode(hash('sha256', $body, true)),
        ], JWT::urlsafeB64Encode($secretKey), 'EdDSA');
    }

    private function rawEd25519PublicKeyToPem(string $raw32Bytes): string
    {
        $asn1Prefix = hex2bin('302a300506032b6570032100');
        $der = $asn1Prefix.$raw32Bytes;
        $base64 = chunk_split(base64_encode($der), 64, "\n");

        return "-----BEGIN PUBLIC KEY-----\n{$base64}-----END PUBLIC KEY-----\n";
    }
}
