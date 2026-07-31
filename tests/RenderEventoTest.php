<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Dps;
use ReflectionProperty;
use stdClass;

/**
 * Eventos (pedRegEvento) — cancelamento e cancelamento por substituição.
 *
 * REGRESSÃO: até a correção, renderEvento() NUNCA gerou XML válido. Faltava o
 * nPedRegEvento (obrigatório) e o Id tinha 59 chars onde o pattern TSIdPedRefEvt
 * exige 62 ("PRE" + 59 dígitos). Nenhum cancelamento jamais foi aceito pelo ADN.
 */
final class RenderEventoTest extends TestCase
{
    private const NS = 'http://www.sped.fazenda.gov.br/nfse';

    /*
    |--------------------------------------------------------------------------
    | Regressão: o XML do evento precisa validar no XSD
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function oCancelamentoValidaContraOXsd(): void
    {
        $this->assertEventoValidoNoXsd($this->stdCancelamento());
    }

    #[Test]
    public function emiteNpedregeventoObrigatorio(): void
    {
        $xpath = $this->renderEvento($this->stdCancelamento());

        $node = $xpath->query('//nfse:infPedReg/nfse:nPedRegEvento')->item(0);

        $this->assertNotNull($node, 'nPedRegEvento é obrigatório no XSD.');
        $this->assertSame('1', $node->nodeValue, 'Cancelamento ocorre uma vez só → 1.');
    }

    #[Test]
    public function posicionaNpedregeventoEntreChnfseEOEvento(): void
    {
        $xpath = $this->renderEvento($this->stdCancelamento());

        $filhos = [];

        foreach ($xpath->query('//nfse:infPedReg/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(
            ['tpAmb', 'verAplic', 'dhEvento', 'CNPJAutor', 'chNFSe', 'nPedRegEvento', 'e101101'],
            $filhos
        );
    }

    #[Test]
    public function oIdDoPedidoTem62Caracteres(): void
    {
        $xpath = $this->renderEvento($this->stdCancelamento());

        $id = $xpath->query('//nfse:infPedReg')->item(0)->getAttribute('Id');

        // TSIdPedRefEvt: PRE + chave(50) + evento(6) + nPedRegEvento(3)
        $this->assertSame(62, mb_strlen($id), 'Id deve ter 62 chars, tem ' . mb_strlen($id) . ": {$id}");
        $this->assertMatchesRegularExpression('/^PRE[0-9]{59}$/', $id);
        $this->assertStringEndsWith('101101001', $id, 'Termina com tipo do evento + nPed zero-padded.');
    }

    #[Test]
    public function oNumeroDoPedidoEConfiguravel(): void
    {
        $std = $this->stdCancelamento();

        $std->infPedReg->nPedRegEvento = 7;

        $xpath = $this->renderEvento($std);

        $this->assertSame('7', $xpath->query('//nfse:nPedRegEvento')->item(0)->nodeValue);

        // No Id vai zero-padded; no elemento, não (TSNum3Dig recusa zero à esquerda).
        $id = $xpath->query('//nfse:infPedReg')->item(0)->getAttribute('Id');
        $this->assertStringEndsWith('101101007', $id);

        $this->assertEventoValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | xDesc é enumeração fixa no XSD
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function derivaOXdescDoCancelamentoIgnorandoOPayload(): void
    {
        $std = $this->stdCancelamento();

        // Payload com grafia errada: não pode vazar para o XML.
        $std->infPedReg->e101101->xDesc = 'texto qualquer que o XSD recusa';

        $xpath = $this->renderEvento($std);

        $this->assertSame(
            'Cancelamento de NFS-e',
            $xpath->query('//nfse:e101101/nfse:xDesc')->item(0)->nodeValue
        );

        $this->assertEventoValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | e105102 — cancelamento por substituição
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function emiteOCorpoDoCancelamentoPorSubstituicao(): void
    {
        $std = $this->stdSubstituicao();

        $xpath = $this->renderEvento($std);

        $this->assertSame(1, $xpath->query('//nfse:infPedReg/nfse:e105102')->length);
        $this->assertSame(0, $xpath->query('//nfse:infPedReg/nfse:e101101')->length);

        $filhos = [];

        foreach ($xpath->query('//nfse:e105102/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(['xDesc', 'cMotivo', 'xMotivo', 'chSubstituta'], $filhos);

        // Grafia exata do XSD — sem cedilha nem til.
        $this->assertSame(
            'Cancelamento de NFS-e por Substituicao',
            $xpath->query('//nfse:e105102/nfse:xDesc')->item(0)->nodeValue
        );

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function oXmotivoDaSubstituicaoEOpcional(): void
    {
        $std = $this->stdSubstituicao();

        unset($std->infPedReg->e105102->xMotivo);

        $xpath = $this->renderEvento($std);

        $this->assertSame(0, $xpath->query('//nfse:e105102/nfse:xMotivo')->length);

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function oIdDaSubstituicaoUsaOCodigo105102(): void
    {
        $id = $this->renderEvento($this->stdSubstituicao())
            ->query('//nfse:infPedReg')->item(0)->getAttribute('Id');

        $this->assertSame(62, mb_strlen($id));
        $this->assertStringEndsWith('105102001', $id);
    }

    /*
    |--------------------------------------------------------------------------
    | Autor: choice CNPJAutor | CPFAutor
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function aceitaCpfDoAutor(): void
    {
        $std = $this->stdCancelamento();

        unset($std->infPedReg->CNPJAutor);
        $std->infPedReg->CPFAutor = '12345678909';

        $xpath = $this->renderEvento($std);

        $this->assertSame(1, $xpath->query('//nfse:infPedReg/nfse:CPFAutor')->length);
        $this->assertSame(0, $xpath->query('//nfse:infPedReg/nfse:CNPJAutor')->length);

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function naoRegistraErrosDePreenchimento(): void
    {
        $dps = new Dps($this->stdCancelamento());
        $dps->renderEvento();

        $errors = (new ReflectionProperty($dps, 'dom'))->getValue($dps)->errors;

        $this->assertSame([], $errors);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function renderEvento(stdClass $std): DOMXPath
    {
        $xml = (new Dps($std))->renderEvento();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', self::NS);

        return $xpath;
    }

    private function assertEventoValidoNoXsd(stdClass $std): void
    {
        $xml = (new Dps($std))->renderEvento();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = $dom->schemaValidate(__DIR__ . '/../storage/schemes/pedRegEvento_v1.01.xsd');
        $erros  = array_map(
            static fn ($erro) => trim($erro->message),
            libxml_get_errors()
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $this->assertTrue($valido, "Evento inválido no XSD v1.01:\n" . implode("\n", $erros));
    }

    private function stdEventoBase(): stdClass
    {
        $std = new stdClass();

        $std->infPedReg            = new stdClass();
        $std->infPedReg->tpAmb     = '2';
        $std->infPedReg->verAplic  = 'TSPD_1.0.1';
        $std->infPedReg->dhEvento  = '2026-07-22T10:00:00-03:00';
        $std->infPedReg->CNPJAutor = '11222333000181';
        $std->infPedReg->chNFSe    = str_repeat('1', 50);

        return $std;
    }

    private function stdCancelamento(): stdClass
    {
        $std = $this->stdEventoBase();

        $std->infPedReg->e101101 = (object) [
            'cMotivo' => '1',
            'xMotivo' => 'Erro na emissao da nota fiscal',
        ];

        return $std;
    }

    private function stdSubstituicao(): stdClass
    {
        $std = $this->stdEventoBase();

        // cMotivo da substituição é TSCodJustSubst (01..05, 99) — com zero à
        // esquerda, diferente do cancelamento, que é TSCodJustCanc (1, 2, 9).
        $std->infPedReg->e105102 = (object) [
            'cMotivo'      => '01',
            'xMotivo'      => 'Substituicao por erro de valor',
            'chSubstituta' => str_repeat('2', 50),
        ];

        return $std;
    }
}
