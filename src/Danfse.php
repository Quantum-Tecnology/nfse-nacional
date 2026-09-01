<?php

namespace QuantumTecnology\NfseNacional;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as QRCodeGenerator;
use chillerlan\QRCode\QROptions;
use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;
use Throwable;

/**
 * DANFSe completo — com a logo da NFS-e e o QR Code de consulta pública.
 *
 * Todo o conteúdo (parsing e seções) vem de {@see Danfse\AbstractDanfse}; aqui
 * ficam apenas a marca e o QR.
 *
 * O QR é gerado EM MEMÓRIA. A versão anterior dependia do Laravel
 * (`public_path()` + facade `LaravelQRCode`, nenhum dos dois declarado no
 * composer) e gravava um PNG por nota em `public/images/`, sem criar o
 * diretório nem limpar o arquivo depois — efeito colateral indesejado numa
 * biblioteca e motivo de a classe não funcionar fora de uma app Laravel.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
class Danfse extends AbstractDanfse
{
    /**
     * Logo padrão da NFS-e, relativa à raiz do pacote.
     * @var string
     */
    protected $logoNfse = 'imgs/nfse_logo.png';

    /**
     * Marca da NFS-e: imagem quando disponível, texto como alternativa.
     *
     * @param float       $x
     * @param float       $y
     * @param string|null $logo Logo informada em render($logo)
     * @return void
     */
    protected function renderMarcaNfse($x, $y, $logo = null)
    {
        $arquivo = $this->resolveLogo($logo);

        if (null === $arquivo) {
            // Sem imagem utilizável, cai no desenho em texto: um cabeçalho sem
            // marca alguma ficaria pior que a alternativa.
            $this->renderMarcaEmTexto($x, $y);

            return;
        }

        $dimensoes = @getimagesize($arquivo);

        if (false === $dimensoes || 0 === (int) $dimensoes[1]) {
            $this->renderMarcaEmTexto($x, $y);

            return;
        }

        // Altura fixa; largura proporcional — assim a marca nunca invade o
        // título central, independentemente do arquivo informado.
        $altura  = 12;
        $largura = round($dimensoes[0] * ($altura / $dimensoes[1]), 0);
        $largura = min($largura, 32);

        try {
            $this->pdf->Image($arquivo, $x + 2, $y + 2, $largura, $altura);
        } catch (Throwable $e) {
            $this->renderMarcaEmTexto($x, $y);
        }
    }

    /**
     * Pinta o QR Code de consulta pública dentro da moldura.
     *
     * @param float $x
     * @param float $y
     * @param float $size
     * @return void
     */
    protected function desenhaQrCode($x, $y, $size)
    {
        $this->pdf->rect($x, $y, $size, $size);

        try {
            $png = (new QRCodeGenerator(new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'outputBase64'    => false,
                'scale'           => 6,
                'quietzoneSize'   => 1,
            ])))->render($this->urlConsultaPublica());
        } catch (Throwable $e) {
            // Sem QR o documento continua válido (a chave de acesso está
            // impressa); melhor a moldura vazia do que derrubar o PDF inteiro.
            return;
        }

        // O FPDF lê imagem de string via wrapper data:// — sem tocar em disco.
        $this->pdf->Image(
            'data://text/plain;base64,' . base64_encode($png),
            $x + 1,
            $y + 1,
            $size - 2,
            $size - 2,
            'png'
        );
    }

    /**
     * Caminho utilizável da logo: a informada, senão a padrão do pacote.
     *
     * @param string|null $logo
     * @return string|null
     */
    private function resolveLogo($logo)
    {
        foreach ([$logo, $this->logomarca, __DIR__ . '/../' . $this->logoNfse] as $candidato) {
            if (is_string($candidato) && '' !== $candidato && is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Marca em texto — mesma da versão simples, usada quando não há imagem.
     *
     * @param float $x
     * @param float $y
     * @return void
     */
    private function renderMarcaEmTexto($x, $y)
    {
        $this->pdf->setFont($this->fontePadrao, 'B', 20);
        $this->pdf->setXY($x + 2, $y + 3);
        $this->pdf->setTextColor(0, 128, 0); // Verde
        $this->pdf->cell(30, 8, 'NFSe', 0, 0, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setTextColor(0, 0, 0);
        $this->pdf->setXY($x + 2, $y + 10);
        $this->pdf->cell(30, 3, 'Nota Fiscal de', 0, 1, 'L');
        $this->pdf->setX($x + 2);
        $this->pdf->cell(30, 3, 'Servico eletronica', 0, 0, 'L');
    }
}
