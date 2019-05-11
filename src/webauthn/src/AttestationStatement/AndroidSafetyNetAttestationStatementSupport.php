<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\AttestationStatement;

use Assert\Assertion;
use Http\Discovery\Psr17FactoryDiscovery;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use function Safe\json_decode;
use function Safe\sprintf;
use Throwable;
use Webauthn\AuthenticatorData;
use Webauthn\TrustPath\CertificateTrustPath;

final class AndroidSafetyNetAttestationStatementSupport implements AttestationStatementSupport
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var RequestFactoryInterface
     */
    private $messageFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var CompactSerializer
     */
    private $jwsSerializer;

    public function __construct(ClientInterface $client, string $apiKey, ?RequestFactoryInterface $requestFactory = null)
    {
        $this->jwsSerializer = new CompactSerializer();
        $this->apiKey = $apiKey;
        $this->messageFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->client = $client;
    }

    public function name(): string
    {
        return 'android-safetynet';
    }

    public function load(array $attestation): AttestationStatement
    {
        Assertion::keyExists($attestation, 'attStmt', 'Invalid attestation object');
        foreach (['ver', 'response'] as $key) {
            Assertion::keyExists($attestation['attStmt'], $key, sprintf('The attestation statement value "%s" is missing.', $key));
        }
        $jws = $this->jwsSerializer->unserialize($attestation['attStmt']['response']);
        $jwsHeader = $jws->getSignature(0)->getProtectedHeader();
        Assertion::keyExists($jwsHeader, 'x5c', 'The response in the attestation statement must contain a "x5c" header.');
        Assertion::notEmpty($jwsHeader['x5c'], 'The "x5c" parameter in the attestation statement response must contain at least one certificate.');
        $certificates = $this->convertCertificatesToPem($jwsHeader['x5c']);
        $parsedCertificate = openssl_x509_parse(current($certificates));
        Assertion::isArray($parsedCertificate, 'Invalid attestation object');
        Assertion::keyExists($parsedCertificate, 'subject', 'Invalid attestation object');
        Assertion::keyExists($parsedCertificate['subject'], 'CN', 'Invalid attestation object');
        Assertion::eq($parsedCertificate['subject']['CN'], 'attest.android.com', 'Invalid attestation object');

        $attestation['attStmt']['jws'] = $jws;

        return AttestationStatement::createBasic(
            $this->name(),
            $attestation['attStmt'],
            new CertificateTrustPath($certificates)
        );
    }

    public function isValid(string $clientDataJSONHash, AttestationStatement $attestationStatement, AuthenticatorData $authenticatorData): bool
    {
        try {
            /** @var JWS $jws */
            $jws = $attestationStatement->get('jws');
            $payload = $jws->getPayload();
            Assertion::notNull($payload, 'Invalid attestation object');
            $payload = JsonConverter::decode($payload);
            Assertion::isArray($payload, 'Invalid attestation object');
            Assertion::keyExists($payload, 'nonce', 'Invalid attestation object');
            Assertion::eq($payload['nonce'], base64_encode(hash('sha256', $authenticatorData->getAuthData().$clientDataJSONHash, true)), 'Invalid attestation object');

            Assertion::keyExists($payload, 'ctsProfileMatch', 'Invalid attestation object');
            Assertion::true($payload['ctsProfileMatch'], 'Invalid attestation object');

            $uri = sprintf('https://www.googleapis.com/androidcheck/v1/attestations/verify?key=%s', urlencode($this->apiKey));
            $requestBody = sprintf('{"signedAttestation":"%s"}', $attestationStatement->get('response'));
            $request = $this->messageFactory->createRequest('POST', $uri);
            $request = $request->withHeader('content-type', 'application/json');
            $request->getBody()->write($requestBody);

            $response = $this->client->sendRequest($request);
            if (!$this->isResponseSuccess($response)) {
                return false;
            }
            $responseBody = $this->getResponseBody($response);
            $responseBodyJson = json_decode($responseBody, true);
            Assertion::keyExists($responseBodyJson, 'isValidSignature', 'Invalid response.');
            Assertion::boolean($responseBodyJson['isValidSignature'], 'Invalid response.');

            return $responseBodyJson['isValidSignature'];
        } catch (Throwable $throwable) {
            return false;
        }
    }

    private function getResponseBody(ResponseInterface $response): string
    {
        $responseBody = '';
        $response->getBody()->rewind();
        do {
            $tmp = $response->getBody()->read(1024);
            if ('' === $tmp) {
                break;
            }
            $responseBody .= $tmp;
        } while (true);

        return $responseBody;
    }

    private function isResponseSuccess(ResponseInterface $response): bool
    {
        if (200 !== $response->getStatusCode() || !$response->hasHeader('content-type')) {
            return false;
        }

        foreach ($response->getHeader('content-type') as $header) {
            if (0 === mb_strpos($header, 'application/json')) {
                return true;
            }
        }

        return false;
    }

    private function convertCertificatesToPem(array $certificates): array
    {
        foreach ($certificates as $k => $v) {
            $tmp = '-----BEGIN CERTIFICATE-----'.PHP_EOL;
            $tmp .= chunk_split($v, 64, PHP_EOL);
            $tmp .= '-----END CERTIFICATE-----'.PHP_EOL;
            $certificates[$k] = $tmp;
        }

        return $certificates;
    }
}
