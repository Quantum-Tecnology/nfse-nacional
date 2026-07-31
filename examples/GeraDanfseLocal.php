<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

use QuantumTecnology\NfseNacional\DanfseSimples;

/*
 * DANFSe gerada LOCALMENTE, a partir do XML — sem depender do ADN.
 *
 * Útil quando o Ambiente Nacional está indisponível, para pré-visualizar antes
 * de transmitir, ou para notas importadas via DFe que não têm PDF oficial.
 *
 * Duas classes, propósitos diferentes:
 *
 *   Danfse         - DANFSe completa (logo + QR Code). ATENÇÃO: hoje depende de
 *                    helpers do Laravel (public_path(), Str) e de um pacote de
 *                    QR Code que NÃO são declarados no composer.json — só
 *                    funciona dentro de uma aplicação Laravel.
 *
 *   DanfseSimples  - renderização tolerante, sem logo/QR. Não exige assinatura
 *                    nem dependências extras: é a que funciona em PHP puro.
 *                    Ideal para rascunhos, prévias e notas recebidas por DFe.
 */

try {
    $xml = file_get_contents('nfse_autorizada.xml');

    // --- Opção 1: DanfseSimples (recomendada fora do Laravel) ---------------
    $danfse = new DanfseSimples($xml, 'P'); // 'P' retrato, 'L' paisagem

    if ($danfse->hasErrors()) {
        // getErrors() aqui devolve uma string (diferente do Dps::getErrors()).
        echo 'Erro ao interpretar o XML: ', $danfse->getErrors(), PHP_EOL;

        exit(1);
    }

    // render() vem do DaCommon (sped-da) e devolve o binário do PDF.
    // Aceita o caminho de um logo como argumento opcional.
    $pdf = $danfse->render();

    file_put_contents('danfse.pdf', $pdf);
    echo 'Gerado: danfse.pdf', PHP_EOL;

    // --- Opção 2: Danfse completa (só dentro de um app Laravel) ------------
    // $danfse = new Danfse($xml, 'P');
    // file_put_contents('danfse-completa.pdf', $danfse->render());
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
