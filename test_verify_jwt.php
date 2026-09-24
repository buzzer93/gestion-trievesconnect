<?php

// Script de diagnostic isole : verifie un JWT EdDSA emis par l'agent Python
// PrintGate, en reproduisant exactement la logique de
// PrintGateJwtVerifier::decodeAndVerifySignature(), sans passer par toute
// la chaine HTTP/Symfony. A lancer depuis la racine du projet Symfony :
//     php test_verify_jwt.php

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- A renseigner avant execution ---------------------------------------

$rawJwt = 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJwb3N0ZS1wcmludGdhdGUtMDEiLCJpc3MiOiJwcmludGdhdGUtYWdlbnQiLCJhdWQiOiJnZXN0aW9uLnRyaWV2ZXNjb25uZWN0LmZyIiwiaWF0IjoxNzgzMzM5NzU0LCJleHAiOjE3ODMzMzk3ODQsImp0aSI6IjY5NDI1NmQyLTBhNGMtNDA4MS04ZWQ0LWUzODg3NGI5ODc5OCIsImJvZHlIYXNoIjoicFJGVUpvTkJoOEIyY09uV21STnVxUTZQQkpQdWpqSXdseUpJQVpiMzlDcyJ9.Dvmj8dlpTOmPaJiaKu0RWJKgDBH6egza0OFYciZpxrnw8h7JNhfyaXz81mBb5PcBzLUGm5lPuBFNZLkHSYXrAg';

$publicKeyPem = <<<PEM
-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAeVVJjahvom1+F2sh4LbTDlepLalCMTf2lx2MhCt1000=
-----END PUBLIC KEY-----
PEM;

// -------------------------------------------------------------------------

function extractRawEd25519PublicKey(string $pem): string
{
    $lines = preg_split('/\r?\n/', trim($pem)) ?: [];
    $base64 = implode('', array_filter(
        $lines,
        static fn (string $line): bool => !str_starts_with(trim($line), '-----'),
    ));

    $der = base64_decode($base64, true);

    if (false === $der || strlen($der) < 32) {
        throw new RuntimeException('PEM invalide');
    }

    return substr($der, -32);
}

echo "=== Etape 1 : decodage brut des segments du JWT (sans verification) ===\n";
$segments = explode('.', $rawJwt);
echo 'Nombre de segments : ' . count($segments) . "\n";

if (3 !== count($segments)) {
    echo "ERREUR : un JWT doit avoir exactement 3 segments (header.payload.signature)\n";
    exit(1);
}

$header = json_decode(JWT::urlsafeB64Decode($segments[0]), true);
$payload = json_decode(JWT::urlsafeB64Decode($segments[1]), true);

echo "Header  : " . json_encode($header) . "\n";
echo "Payload : " . json_encode($payload) . "\n";
echo 'Signature (longueur brute decodee) : ' . strlen(JWT::urlsafeB64Decode($segments[2])) . " octets (attendu : 64 pour Ed25519)\n\n";

echo "=== Etape 2 : extraction de la cle publique ===\n";
$rawKey = extractRawEd25519PublicKey($publicKeyPem);
echo 'Cle publique brute (longueur) : ' . strlen($rawKey) . " octets (attendu : 32)\n";
echo 'Cle publique brute (hex)      : ' . bin2hex($rawKey) . "\n";

$keyMaterialForFirebase = JWT::urlsafeB64Encode($rawKey);
echo 'Cle re-encodee base64url (transmise a Key)  : ' . $keyMaterialForFirebase . "\n\n";

echo "=== Etape 3 : verification via sodium directement (sans firebase/php-jwt) ===\n";
$signingInput = $segments[0] . '.' . $segments[1];
$signature = JWT::urlsafeB64Decode($segments[2]);

if (64 !== strlen($signature)) {
    echo "ATTENTION : signature de " . strlen($signature) . " octets, 64 attendus pour Ed25519 -- probable souci d'encodage cote emetteur.\n";
}

try {
    $sodiumOk = sodium_crypto_sign_verify_detached($signature, $signingInput, $rawKey);
    echo 'sodium_crypto_sign_verify_detached() direct : ' . ($sodiumOk ? 'VALIDE' : 'INVALIDE') . "\n\n";
} catch (\Throwable $e) {
    echo 'Exception sodium directe : ' . $e->getMessage() . "\n\n";
}

echo "=== Etape 4 : verification via firebase/php-jwt (chemin reel de l'appli) ===\n";
try {
    $decoded = JWT::decode($rawJwt, new Key($keyMaterialForFirebase, 'EdDSA'));
    echo "SUCCES : " . json_encode($decoded) . "\n";
} catch (\Throwable $e) {
    echo 'ECHEC : ' . get_class($e) . ' -- ' . $e->getMessage() . "\n";
}
