<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\DecisionIssuerAddressData;
use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;

/**
 * Builds the complete unsigned NFS-e required by the decision bypass.
 */
final class DecisionNfseBuilder
{
    private const NS = 'http://www.sped.fazenda.gov.br/nfse';

    public function build(DecisionNfseData $data): string
    {
        $this->validate($data);

        $dpsDocument = new \DOMDocument();
        if (!$dpsDocument->loadXML((new XmlBuilder())->buildDps($data->dps), LIBXML_NONET)) {
            throw new \RuntimeException('Unable to parse generated DPS.');
        }

        $xpath = new \DOMXPath($dpsDocument);
        $xpath->registerNamespace('n', self::NS);
        $dhEmi = trim((string) $xpath->evaluate('string(/n:DPS/n:infDPS/n:dhEmi)'));
        if ($dhEmi === '') {
            throw new \RuntimeException('Generated DPS does not contain dhEmi.');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NS, 'NFSe');
        $root->setAttribute('versao', '1.01');
        $document->appendChild($root);

        $info = $document->createElement('infNFSe');
        $info->setAttribute('Id', $this->identifier($data, $dhEmi));
        $root->appendChild($info);

        $this->append($document, $info, 'xLocEmi', $data->localEmissao);
        $this->append($document, $info, 'xLocPrestacao', $data->localPrestacao);
        $this->append($document, $info, 'nNFSe', $data->numeroNfse);
        if ($data->codigoLocalIncidencia !== null) {
            $this->append($document, $info, 'cLocIncid', $data->codigoLocalIncidencia);
            $this->append($document, $info, 'xLocIncid', (string) $data->localIncidencia);
        }
        $this->append($document, $info, 'xTribNac', $data->descricaoTributacaoNacional);
        $this->optional($document, $info, 'xTribMun', $data->descricaoTributacaoMunicipal);
        $this->optional($document, $info, 'xNBS', $data->descricaoNbs);
        $this->append($document, $info, 'verAplic', $data->versaoAplicativo);
        $this->append($document, $info, 'ambGer', '2');
        $this->append($document, $info, 'tpEmis', '1');
        if ($data->processoEmissao !== null) {
            $this->append($document, $info, 'procEmi', (string) $data->processoEmissao);
        }
        $this->append($document, $info, 'cStat', '102');
        $this->append($document, $info, 'dhProc', $dhEmi);
        $this->append($document, $info, 'nDFSe', $data->numeroDfse);
        $info->appendChild($this->issuer($document, $data));
        $info->appendChild($this->values($document, $data));
        $this->optional($document, $info, 'xOutInf', $data->outrasInformacoes);

        $dpsRoot = $dpsDocument->documentElement;
        if (!$dpsRoot instanceof \DOMElement) {
            throw new \RuntimeException('Generated DPS has no root element.');
        }
        $info->appendChild($document->importNode($dpsRoot, true));

        return $document->saveXML() ?: '';
    }

    public static function accessKeyCheckDigit(string $base): int
    {
        if (preg_match('/^\d{49}$/', $base) !== 1) {
            throw new \InvalidArgumentException('NFS-e access-key base must contain exactly 49 digits.');
        }
        $sum = 0;
        $weight = 2;
        for ($i = strlen($base) - 1; $i >= 0; --$i) {
            $sum += ((int) $base[$i]) * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $remainder = $sum % 11;
        return $remainder <= 1 ? 0 : 11 - $remainder;
    }

    private function identifier(DecisionNfseData $data, string $dhEmi): string
    {
        $base = $data->dps->municipioIbge
            . '2'
            . '2'
            . $data->dps->cnpjPrestador
            . str_pad($data->numeroNfse, 13, '0', STR_PAD_LEFT)
            . (new \DateTimeImmutable($dhEmi))->format('ym')
            . $data->codigoNumerico;
        return 'NFS' . $base . self::accessKeyCheckDigit($base);
    }

    private function issuer(\DOMDocument $doc, DecisionNfseData $data): \DOMElement
    {
        $node = $doc->createElement('emit');
        $this->append($doc, $node, 'CNPJ', $data->dps->cnpjPrestador);
        $this->optional($doc, $node, 'IM', $data->emitenteInscricaoMunicipal);
        $this->append($doc, $node, 'xNome', $data->emitenteNome);
        $this->optional($doc, $node, 'xFant', $data->emitenteNomeFantasia);
        $address = $doc->createElement('enderNac');
        $this->appendAddress($doc, $address, $data->emitenteEndereco);
        $node->appendChild($address);
        $this->optional($doc, $node, 'fone', $data->emitenteTelefone);
        $this->optional($doc, $node, 'email', $data->emitenteEmail);
        return $node;
    }

    private function appendAddress(\DOMDocument $doc, \DOMElement $node, DecisionIssuerAddressData $data): void
    {
        $this->append($doc, $node, 'xLgr', $data->logradouro);
        $this->append($doc, $node, 'nro', $data->numero);
        $this->optional($doc, $node, 'xCpl', $data->complemento);
        $this->append($doc, $node, 'xBairro', $data->bairro);
        $this->append($doc, $node, 'cMun', $data->municipioIbge);
        $this->append($doc, $node, 'UF', strtoupper($data->uf));
        $this->append($doc, $node, 'CEP', preg_replace('/\D+/', '', $data->cep) ?: '');
    }

    private function values(\DOMDocument $doc, DecisionNfseData $data): \DOMElement
    {
        $node = $doc->createElement('valores');
        $this->optional($doc, $node, 'vBC', $data->baseCalculo);
        $this->optional($doc, $node, 'pAliqAplic', $data->aliquotaAplicada);
        $this->optional($doc, $node, 'vISSQN', $data->valorIssqn);
        $this->optional($doc, $node, 'vTotalRet', $data->valorTotalRetido);
        $this->append($doc, $node, 'vLiq', $data->valorLiquido);
        return $node;
    }

    private function validate(DecisionNfseData $data): void
    {
        if (preg_match('/^[1-9]\d{0,12}$/', $data->numeroNfse) !== 1) {
            throw new \InvalidArgumentException('Decision-flow NFS-e number must contain 1 to 13 digits and cannot start with zero.');
        }
        if (preg_match('/^\d{9}$/', $data->codigoNumerico) !== 1) {
            throw new \InvalidArgumentException('Decision-flow numeric code must contain exactly 9 digits.');
        }
        if (preg_match('/^\d{14}$/', $data->dps->cnpjPrestador) !== 1) {
            throw new \InvalidArgumentException('Decision-flow schema v1.01 requires a numeric 14-digit CNPJ.');
        }
        if (($data->codigoLocalIncidencia === null) !== ($data->localIncidencia === null)) {
            throw new \InvalidArgumentException('Incidence municipality code and description must be provided together.');
        }
        if ($data->codigoLocalIncidencia !== null && preg_match('/^\d{7}$/', $data->codigoLocalIncidencia) !== 1) {
            throw new \InvalidArgumentException('Incidence municipality code must contain 7 digits.');
        }
        if (
            $data->dps->ibsCbsFinalidade !== null
            || $data->dps->ibsCbsIndFinal !== null
            || $data->dps->ibsCbsCodigoIndicadorOperacao !== ''
            || $data->dps->ibsCbsIndDest !== null
            || $data->dps->ibsCbsCst !== ''
            || $data->dps->ibsCbsClassificacaoTributaria !== ''
        ) {
            throw new \LogicException('Decision-flow IBS/CBS requires complete NFS-e-level calculated values and is not yet modeled.');
        }
        if ($data->codigoLocalIncidencia !== null && $data->aliquotaAplicada === null) {
            throw new \InvalidArgumentException('ISSQN rate is required when an incidence municipality is informed.');
        }
        foreach ([
            'localEmissao' => $data->localEmissao,
            'localPrestacao' => $data->localPrestacao,
            'descricaoTributacaoNacional' => $data->descricaoTributacaoNacional,
            'emitenteNome' => $data->emitenteNome,
            'valorLiquido' => $data->valorLiquido,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException('Decision-flow field cannot be empty: ' . $field);
            }
        }
    }

    private function append(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->textContent = $value;
        $parent->appendChild($node);
    }

    private function optional(\DOMDocument $doc, \DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->append($doc, $parent, $name, $value);
        }
    }
}
