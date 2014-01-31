<?php

namespace CmCIC\Controller;

use CmCIC\CmCIC;
use CmCIC\Model\ConfigInterface;
use Thelia\Controller\Front\BaseFrontController;
use CmCIC\Model\Config;
use Thelia\Core\HttpFoundation\Request;

class CmcicPayController extends BaseFrontController {

    const CMCIC_CTLHMAC = "V1.04.sha1.php--[CtlHmac%s%s]-%s";
    const CMCIC_CTLHMACSTR = "CtlHmac%s%s";
    const CMCIC_CGI2_RECEIPT = "version=2\ncdr=%s";
    const CMCIC_CGI2_MACOK = "0";
    const CMCIC_CGI2_MACNOTOK = "1\n";
    const CMCIC_CGI2_FIELDS = "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*";
    const CMCIC_CGI1_FIELDS = "%s*%s*%s%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s";
    const CMCIC_URLPAIEMENT = "%s/%s";

    protected $sVersion;
    protected $sNumero;
    protected $sCodeSociete;
    protected $sLangue;
    protected $sUrlOK;
    protected $sUrlKO;
    protected $sUrlPaiement;

    protected $_sKey;
    protected $_sUsableKey;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    public function getSession() {
        return $this->getRequest()->getSession();
    }

    public function getRequest() {
        return $this->request;
    }

    public function goto_paypage(ConfigInterface $config) {
        $a = $config::read(CmCIC::JSON_CONFIG_PATH);
        $this->render("gotobankservice");

    }

    public function hydrate($sLanguage = "FR") {
        $config = Config::read(CmCIC::JSON_CONFIG_PATH);

        $this->sVersion = $c['CMCIC_VERSION'];
        $this->_sKey = $c['CMCIC_Key'];
        $this->sNumero = $c['CMCIC_TPE'];
        $this->sUrlPaiement = $c['CMCIC_SERVEUR'] . $config['CMCIC_URLPAIEMENT'];

        $this->sCodeSociete = $c['CMCIC_CODESOCIETE'];
        $this->sLangue = $sLanguage;

        $this->sUrlOK = $c['CMCIC_URLOK'];
        $this->sUrlKO = $c['CMCIC_URLKO'];
        $this->_sUsableKey = $this->_getUsableKey();
    }

    public function getKey() {
        return $this->_sKey;
    }

    private function _getUsableKey(){

        $hexStrKey  = substr($this->getKey(), 0, 38);
        $hexFinal   = "" . substr($this->getKey(), 38, 2) . "00";

        $cca0=ord($hexFinal);

        if ($cca0>70 && $cca0<97)
            $hexStrKey .= chr($cca0-23) . substr($hexFinal, 1, 1);
        else {
            if (substr($hexFinal, 1, 1)=="M")
                $hexStrKey .= substr($hexFinal, 0, 1) . "0";
            else
                $hexStrKey .= substr($hexFinal, 0, 2);
        }


        return pack("H*", $hexStrKey);
    }

    public function computeHmac($sData) {

        return strtolower(hash_hmac("sha1", $sData, $this->_sUsableKey));

        // If you have don't have PHP 5 >= 5.1.2 and PECL hash >= 1.1
        // you may use the hmac_sha1 function defined below
        //return strtolower($this->hmac_sha1($this->_sUsableKey, $sData));
    }

    public function hmac_sha1 ($key, $data) {

        $length = 64; // block length for SHA1
        if (strlen($key) > $length) {
            $key = pack("H*",sha1($key));
        }
        $key  = str_pad($key, $length, chr(0x00));
        $ipad = str_pad('', $length, chr(0x36));
        $opad = str_pad('', $length, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        return sha1($k_opad  . pack("H*",sha1($k_ipad . $data)));
    }

    public static function HtmlEncode ($data)
    {
        $SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
        $result = "";
        for ($i=0; $i<strlen($data); $i++)
        {
            if (strchr($SAFE_OUT_CHARS, $data{$i})) {
                $result .= $data{$i};
            }
            else if (($var = bin2hex(substr($data,$i,1))) <= "7F"){
                $result .= "&#x" . $var . ";";
            }
            else
                $result .= $data{$i};

        }
        return $result;
    }
}

