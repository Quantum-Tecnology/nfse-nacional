<?php

namespace QuantumTecnology\NfseNacional;

use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;

/**
 * DANFSe portátil — PHP puro, sem dependências opcionais.
 *
 * Desenha a marca "NFSe" em texto (não exige arquivo de imagem) e não gera o
 * QR Code, apenas sua moldura. Use quando não houver a extensão/pacote de QR
 * disponível; caso contrário prefira {@see Danfse}, que é o documento completo.
 *
 * Todo o resto — parsing e todas as seções — vem de {@see AbstractDanfse}.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
class DanfseSimples extends AbstractDanfse
{
    /**
     * Marca da NFS-e desenhada em texto, para não depender de arquivo externo.
     *
     * @param float       $x
     * @param float       $y
     * @param string|null $logo Ignorado nesta versão
     * @return void
     */
    protected function renderMarcaNfse($x, $y, $logo = null)
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
