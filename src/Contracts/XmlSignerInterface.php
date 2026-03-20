<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

interface XmlSignerInterface
{
    /**
     * Sign the given XML string with the PFX certificate identified by the given CNPJ.
     *
     * The signer must retrieve the PFX file path and its password from the
     * SecretStoreInterface it was constructed with, and must NOT accept the
     * password as a plain-text argument.
     */
    public function sign(string $xml, string $cnpj): string;
}
