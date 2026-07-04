<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\PrintGate;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests de l'étape 4 (module minimal) : le contrôleur ne fait encore
 * aucune vérification JWT -- le test testAccessWithoutJwtIsCurrentlyAllowed
 * documente volontairement cet état temporaire et devra être inversé à
 * l'étape 5 une fois PrintGateJwtVerifier branché (cf. TODO dans le
 * contrôleur et le manager).
 */
final class PrintAuthorizationControllerTest extends WebTestCase
{
    public function testNominalRequestReturnsAuthorizationDecision(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/printgate/authorize',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'identifier' => 'j.dupont',
                'computerId' => 'POSTE-LINUX-01',
                'hostname' => 'poste-linux-01',
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
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('authorizedImpression', $payload);
    }

    public function testInvalidPayloadReturnsBadRequest(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/printgate/authorize',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'identifier' => '',
                // computerId manquant volontairement
                'printJob' => [
                    'jobId' => 42,
                    'printerName' => 'Imprimante-WiFi',
                    'documentName' => 'document.pdf',
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testWrongHttpMethodReturnsMethodNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/printgate/authorize');

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * TODO étape 5 : ce test devra être inversé (accès sans JWT doit
     * devenir un 401) une fois PrintGateJwtVerifier branché en amont
     * du contrôleur. Il documente ici l'état actuel, volontairement
     * incomplet, de l'étape 4.
     */
    public function testAccessWithoutJwtIsCurrentlyAllowed(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/printgate/authorize',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'identifier' => 'j.dupont',
                'computerId' => 'POSTE-LINUX-01',
                'hostname' => 'poste-linux-01',
                'printJob' => [
                    'jobId' => 1,
                    'printerName' => 'Imprimante-WiFi',
                    'documentName' => 'document.pdf',
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
    }
}
