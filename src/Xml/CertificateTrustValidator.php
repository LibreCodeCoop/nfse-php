<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Contracts\CertificateRevocationCheckerInterface;
use LibreCodeCoop\NfsePHP\Dto\CertificateTrustResult;

/**
 * Validates signer-certificate trust separately from XMLDSig integrity.
 *
 * Trust anchors are supplied by the consumer. This class does not claim
 * ICP-Brasil trust unless the configured anchor set actually represents the
 * trust policy the consumer intends to enforce.
 */
final class CertificateTrustValidator
{
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * @param list<string> $trustAnchorPaths
     */
    public function __construct(
        private readonly array $trustAnchorPaths = [],
        private readonly ?CertificateRevocationCheckerInterface $revocationChecker = null,
        private readonly ?int $validationTime = null,
        private readonly ?XmlSignatureVerifier $signatureVerifier = null,
    ) {
    }

    public function validate(string $xml): CertificateTrustResult
    {
        $certificatePem = $this->extractCertificatePem($xml);
        $certificatePresent = $certificatePem !== null;
        $signatureValid = ($this->signatureVerifier ?? new XmlSignatureVerifier())->verify($xml);

        if (!$certificatePresent) {
            return new CertificateTrustResult(
                signatureValid: $signatureValid,
                certificatePresent: false,
                certificateTimeValid: false,
                chainTrusted: false,
                revocationStatus: 'not_checked',
                errors: ['XML signature does not contain an X509 certificate in KeyInfo.'],
            );
        }

        if (!$signatureValid) {
            return new CertificateTrustResult(
                signatureValid: false,
                certificatePresent: true,
                certificateTimeValid: false,
                chainTrusted: false,
                revocationStatus: 'not_checked',
                errors: ['XMLDSig integrity verification failed.'],
            );
        }

        $parsed = openssl_x509_parse($certificatePem);
        if (!is_array($parsed)) {
            return new CertificateTrustResult(
                signatureValid: true,
                certificatePresent: true,
                certificateTimeValid: false,
                chainTrusted: false,
                revocationStatus: 'not_checked',
                errors: ['Unable to parse the signing certificate.'],
            );
        }

        $now = $this->validationTime ?? time();
        $validFrom = is_numeric($parsed['validFrom_time_t'] ?? null) ? (int) $parsed['validFrom_time_t'] : 0;
        $validTo = is_numeric($parsed['validTo_time_t'] ?? null) ? (int) $parsed['validTo_time_t'] : 0;
        $errors = [];
        $certificateTimeValid = true;

        if ($validFrom <= 0 || $validTo <= 0) {
            $certificateTimeValid = false;
            $errors[] = 'Signing certificate validity interval is unavailable.';
        } elseif ($now < $validFrom) {
            $certificateTimeValid = false;
            $errors[] = 'Signing certificate is not yet valid.';
        } elseif ($now > $validTo) {
            $certificateTimeValid = false;
            $errors[] = 'Signing certificate is expired.';
        }

        $chainTrusted = false;
        if ($this->trustAnchorPaths === []) {
            $errors[] = 'No certificate trust anchors were configured.';
        } else {
            $purposeResult = openssl_x509_checkpurpose(
                $certificatePem,
                X509_PURPOSE_ANY,
                $this->trustAnchorPaths,
            );
            $chainTrusted = $purposeResult === true || $purposeResult === 1;

            if (!$chainTrusted) {
                $errors[] = 'Signing certificate chain is not trusted by the configured anchors.';
            }
        }

        $revocationStatus = 'not_checked';
        if ($this->revocationChecker !== null) {
            try {
                $revocationStatus = $this->revocationChecker->check($certificatePem);

                if (!in_array($revocationStatus, ['good', 'revoked', 'unknown'], true)) {
                    $errors[] = 'Revocation checker returned an unsupported status.';
                    $revocationStatus = 'unknown';
                } elseif ($revocationStatus === 'revoked') {
                    $errors[] = 'Signing certificate is revoked.';
                } elseif ($revocationStatus === 'unknown') {
                    $errors[] = 'Signing certificate revocation status could not be determined.';
                }
            } catch (\Throwable $e) {
                $revocationStatus = 'unknown';
                $errors[] = 'Certificate revocation check failed: ' . $e->getMessage();
            }
        }

        return new CertificateTrustResult(
            signatureValid: true,
            certificatePresent: true,
            certificateTimeValid: $certificateTimeValid,
            chainTrusted: $chainTrusted,
            revocationStatus: $revocationStatus,
            errors: $errors,
        );
    }

    private function extractCertificatePem(string $xml): ?string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('ds', self::DS_NS);

        $certificateNode = $xpath
            ->query('//ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate')
            ?->item(0);

        if (!$certificateNode instanceof \DOMElement) {
            return null;
        }

        $body = preg_replace('/\s+/', '', $certificateNode->textContent) ?? '';
        if ($body === '' || base64_decode($body, true) === false) {
            return null;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }
}
