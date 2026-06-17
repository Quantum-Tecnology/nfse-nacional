<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

use NFePHP\Common\Certificate;

class Tools extends RestCurl
{
    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }

    public function consultarNfseChave($chave, $encoding = true)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_nfse'));
        $retorno = $this->getData($operacao);

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            $base_decode = base64_decode($retorno['nfseXmlGZipB64']);
            $gz_decode = gzdecode($base_decode);
            return $encoding ? mb_convert_encoding($gz_decode, 'ISO-8859-1') : $gz_decode;
        }

        return null;
    }

    public function consultarDpsChave($chave)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_dps'));
        $retorno = $this->getData($operacao);

        return $retorno;
    }

    public function consultarNfseEventos($chave, $tipoEvento = null, $nSequencial = null)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_eventos'));

        if ($tipoEvento) {
            $operacao = str_replace("{tipoEvento}", (string) $tipoEvento, $operacao);
        } else {
            // Sem tipo de evento: remove os dois segmentos opcionais da URL.
            $operacao = str_replace("/{tipoEvento}/{nSequencial}", "", $operacao);
        }

        if ($nSequencial) {
            $operacao = str_replace("{nSequencial}", (string) $nSequencial, $operacao);
        } else {
            $operacao = str_replace("/{nSequencial}", "", $operacao);
        }

        $retorno = $this->getData($operacao);

        return $retorno;
    }

    public function consultarDanfse(string $chave)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_danfse'));
        $retorno = $this->getData($operacao, null, 2);

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            return $retorno;
        }

        if (empty($retorno)) {
            return $this->consultarDanfseNfse($chave);
        }

        return null;
    }

    /**
     * Consulta o DANFSe via NFSe caso o serviço direto falhe.
     *
     * @param string $chave
     *
     * @return array|binary|null
     */
    public function consultarDanfseNfse($chave)
    {
        $operacao = $this->getOperation('consultar_danfse_nfse_certificado');
        $retorno = $this->getData($operacao, null, 3);
        if(isset($retorno) and isset($retorno['sucesso']) and $retorno['sucesso']==true){
            $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_danfse_nfse_download'));
            $retorno = $this->getData($operacao, null, 3);
        }

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            return $retorno;
        }

        return null;
    }

    public function enviaDps($content): array
    {
        $content = $this->sign($content, 'infDPS', '', 'DPS');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz      = gzencode($content);
        $data    = base64_encode($gz);
        $dados   = [
            'dpsXmlGZipB64' => $data,
        ];
        $operacao = $this->getOperation('emitir_nfse');
        $retorno = $this->postData($operacao, json_encode($dados));
        return $retorno;
    }

    public function cancelaNfse($std)
    {
        $dps     = new Dps($std);
        $content = $dps->renderEvento($std);
        $content = $this->sign($content, 'infPedReg', '', 'pedRegEvento');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz      = gzencode($content);
        $data    = base64_encode($gz);
        $dados   = [
            'pedidoRegistroEventoXmlGZipB64' => $data,
        ];
        $operacao = str_replace("{chave}", $std->infPedReg->chNFSe, $this->getOperation('cancelar_nfse'));
        $retorno = $this->postData($operacao, json_encode($dados));
        return $retorno;
    }

}
