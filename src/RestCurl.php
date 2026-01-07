<?php

namespace Hadder\NfseNacional;

use Exception;
use Hadder\NfseNacional\Common\RestBase;
use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Signer;

class RestCurl extends RestBase
{
    const URL_SEFIN_HOMOLOGACAO = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
    const URL_SEFIN_PRODUCAO = 'https://sefin.nfse.gov.br/sefinnacional';
    const URL_ADN_HOMOLOGACAO = 'https://adn.producaorestrita.nfse.gov.br';
    const URL_ADN_PRODUCAO = 'https://adn.nfse.gov.br';
    const URL_NFSE_HOMOLOGACAO = 'https://www.producaorestrita.nfse.gov.br/EmissorNacional';
    const URL_NFSE_PRODUCAO = 'https://www.nfse.gov.br/EmissorNacional';
    private mixed $config;
    private string $url_api;
    private $connection_timeout = 30;
    private $timeout = 30;
    private $httpver;
    public string $soaperror;
    public int $soaperror_code;
    public array $soapinfo;
    public string $responseHead;
    public string $responseBody;
    private string $requestHead;
    private string $cookies = '';

    protected $canonical = [true, false, null, null];

    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($cert);
        $this->config = json_decode($config);
        $this->certificate = $cert;
        //        $this->wsobj = $this->loadWsobj($this->config->cmun);
    }

    /**
     * @param $operacao
     * @param $data
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
     * @return mixed|string
     */
    public function getData($operacao, $data = null, $origem = 1)
    {
        $this->resolveUrl($origem);
        $this->saveTemporarilyKeyFiles();
        try {
            $msgSize = $data ? strlen($data) : 0;
            $parameters = [
                "Content-Type: application/json;charset=utf-8;",
                "Content-length: $msgSize"
            ];
            $oCurl = curl_init();
            curl_setopt($oCurl, CURLOPT_URL, $this->url_api . '/' . $operacao);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, $this->httpver);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            if (!empty($this->security_level)) {
                curl_setopt($oCurl, CURLOPT_SSL_CIPHER_LIST, "{$this->security_level}");
            }
            //            if (!$this->disablesec) {
            //                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);
            //                if (!empty($this->casefaz)) {
            //                    if (is_file($this->casefaz)) {
            //                        curl_setopt($oCurl, CURLOPT_CAINFO, $this->casefaz);
            //                    }
            //                }
            //            }
            curl_setopt($oCurl, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
            curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
            curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
            if (!empty($this->temppass)) {
                curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
            }
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
            if (!empty($data)) {
                curl_setopt($oCurl, CURLOPT_POST, 1);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $data);
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }elseif ($origem === 3 && !empty($this->cookies)) {
                $parameters[] = 'Cookie: ' . $this->cookies;
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);

            $this->soaperror = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($oCurl, CURLINFO_CONTENT_TYPE);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            //detecta redirect, conseguiu logar com certificado na origem 3 e pega cookies
            if($origem==3 and $httpcode==302) {
                $this->captureCookies($this->responseHead, $origem);
                return ['sucesso'=>true];
            }
            if ($contentType == 'application/pdf') {
                return $this->responseBody;
            } else {
                return json_decode($this->responseBody, true);
            }
        } catch (Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
    }

    /**
     * @param $operacao
     * @param $data
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
     * @return mixed|string
     */
    public function postData($operacao, $data, $origem = 1)
    {
        $this->resolveUrl($origem);
        $this->saveTemporarilyKeyFiles();
        try {
            $msgSize = $data ? strlen($data) : 0;
            $parameters = [
                //                'Accept: */*; ',
                'Content-Type: application/json',
                //                "Content-Type: application/x-www-form-urlencoded;charset=utf-8;",
                'Content-length: ' . $msgSize,
            ];
            //            $this->requestHead = implode("\n", $parameters);
            $oCurl = curl_init();
            curl_setopt($oCurl, CURLOPT_URL, $this->url_api . '/' . $operacao);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, $this->httpver);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            if (!empty($this->security_level)) {
                curl_setopt($oCurl, CURLOPT_SSL_CIPHER_LIST, "{$this->security_level}");
            }

            curl_setopt($oCurl, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
            curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
            curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
            if (!empty($this->temppass)) {
                curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
            }
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
            if (!empty($data)) {
                curl_setopt($oCurl, CURLOPT_POST, 1);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $data);
                //curl_setopt($oCurl, CURLOPT_POSTFIELDS, http_build_query($data)); // Dados para enviar no POST
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);

            $this->soaperror = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            curl_close($oCurl);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            return json_decode($this->responseBody, true);
        } catch (Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setConnectionTimeout($connection_timeout)
    {
        $this->connection_timeout = $connection_timeout;
    }

    /**
     * Sign XML passing in content
     * @param string $content
     * @param string $tagname
     * @param string $mark
     * @return string XML signed
     */
    public function sign(string $content, string $tagname, ?string $mark, $rootname)
    {
        if (empty($mark)) {
            $mark = 'Id';
        }
        $xml = Signer::sign(
            $this->certificate,
            $content,
            $tagname,
            $mark,
            OPENSSL_ALGO_SHA1,
            $this->canonical,
            $rootname
        );
        return $xml;
    }

    private function resolveUrl(int $origem = 0)
    {
        switch ($origem) {
            case 1: // SEFIN
            default:
                $this->url_api = self::URL_SEFIN_HOMOLOGACAO;
                if ($this->config->tpamb === 1) {
                    $this->url_api = self::URL_SEFIN_PRODUCAO;
                }
                break;
            case 2: // ADN
                $this->url_api = self::URL_ADN_HOMOLOGACAO;
                if ($this->config->tpamb === 1) {
                    $this->url_api = self::URL_ADN_PRODUCAO;
                }
                break;
            case 3: // NFSE
                $this->url_api = self::URL_NFSE_HOMOLOGACAO;
                if ($this->config->tpamb === 1) {
                    $this->url_api = self::URL_NFSE_PRODUCAO;
                }
                break;
        }

    }

    private function captureCookies(string $headers, int $origem): void
    {
        if ($origem !== 3) {
            return;
        }
        if (!preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)/mi', $headers, $matches)) {
            return;
        }
        $cookies = array_map('trim', $matches[1]);
        if (!empty($cookies)) {
            $this->cookies = implode('; ', $cookies);
        }
    }
}
