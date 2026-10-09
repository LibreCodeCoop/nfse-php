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
