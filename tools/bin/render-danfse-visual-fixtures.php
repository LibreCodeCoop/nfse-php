<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$outputDir = $argv[1] ?? dirname(__DIR__, 2) . '/build/danfse-visual';
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Failed creating DANFSe output directory');
}

$xml = file_get_contents(dirname(__DIR__, 2) . '/tests/fixtures/nfse_exemplo.xml');
if ($xml === false) {
    throw new RuntimeException('Synthetic NFS-e fixture not found');
}
$generator = new DanfseGenerator();
$pdf = $generator->generateFromXml($xml);
file_put_contents($outputDir . '/danfse-reference.pdf', $pdf);

// Stress-test description overflow/page breaking without using real taxpayer
// data or changing the fiscal totals in the synthetic authorized fixture.
$doc = new DOMDocument();
if (!$doc->loadXML($xml, LIBXML_NONET)) {
    throw new RuntimeException('Invalid synthetic NFS-e fixture');
}
$xpath = new DOMXPath($doc);
$descriptions = $xpath->query('//*[local-name()="xDescServ"]');
if ($descriptions === false || $descriptions->length === 0) {
    throw new RuntimeException('Synthetic fixture has no service description');
}
$descriptions->item(0)->nodeValue = str_repeat(
    "DESCRIÇÃO EXTENSA - serviço contratado\nEtapa técnica, relatório e acompanhamento.\n",
    45,
);
$longXml = $doc->saveXML();
if (!is_string($longXml)) {
    throw new RuntimeException('Could not serialize long synthetic NFS-e');
}
$longPdf = $generator->generateFromXml($longXml);
file_put_contents($outputDir . '/danfse-long-description.pdf', $longPdf);

foreach ([$pdf, $longPdf] as $rendered) {
    if (!str_starts_with($rendered, '%PDF-')) {
        throw new RuntimeException('DANFSe generator did not return a PDF');
    }
}

/**
 * Additional independent synthetic PDFs for the municipal conditional rows.
 * These fixtures exercise presentation only; no XML is transmitted or signed.
 */
foreach (['regime-zero', 'regime-nine', 'regime-absent', 'immunity-zero', 'benefit-zero', 'no-iss-incidence'] as $scenario) {
    $caseDoc = new DOMDocument();
    if (!$caseDoc->loadXML($xml, LIBXML_NONET)) {
        throw new RuntimeException('Invalid synthetic NFS-e base fixture');
    }

    $caseXPath = new DOMXPath($caseDoc);
    $regime = $caseXPath->query('//*[local-name()="regEspTrib"]')?->item(0);
    $municipalTax = $caseXPath->query('//*[local-name()="tribISSQN"]')?->item(0);
    if (!$regime instanceof DOMElement || !$municipalTax instanceof DOMElement) {
        throw new RuntimeException('Synthetic fixture lacks municipal tax nodes');
    }

    if ($scenario === 'regime-zero') {
        $regime->nodeValue = '0';
    } elseif ($scenario === 'regime-nine') {
        $regime->nodeValue = '9';
    } elseif ($scenario === 'regime-absent' || $scenario === 'immunity-zero') {
        $regime->parentNode?->removeChild($regime);
    }

    if ($scenario === 'immunity-zero') {
        $municipalTax->nodeValue = '2';
        $immunity = $caseDoc->createElementNS($municipalTax->namespaceURI, 'tpImunidade', '0');
        $municipalTax->parentNode?->insertBefore($immunity, $municipalTax->nextSibling);
    }

    if ($scenario === 'benefit-zero') {
        $values = $caseXPath->query('//*[local-name()="infNFSe"]/*[local-name()="valores"]')?->item(0);
        if (!$values instanceof DOMElement) {
            throw new RuntimeException('Synthetic fixture lacks authorized totals');
        }

        $benefit = $caseDoc->createElementNS($values->namespaceURI, 'vCalcBM', '0.00');
        $values->insertBefore($benefit, $values->firstChild);
    }

    if ($scenario === 'no-iss-incidence') {
        $municipalTax->nodeValue = '4';
    }

    $caseXml = $caseDoc->saveXML();
    if (!is_string($caseXml)) {
        throw new RuntimeException('Could not serialize municipal synthetic fixture');
    }

    $casePdf = $generator->generateFromXml($caseXml);
    if (!str_starts_with($casePdf, '%PDF-')) {
        throw new RuntimeException('Municipal synthetic scenario did not generate a PDF');
    }
    file_put_contents($outputDir . '/danfse-' . $scenario . '.pdf', $casePdf);
}
