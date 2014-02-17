<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace CmCIC\Controller;

use CmCIC\CmCIC;
use Thelia\Core\Translation\Translator;
use Thelia\Controller\Front\BaseFrontController;
use CmCIC\Model\Config;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;
use Thelia\Model\OrderStatusQuery;

class CmcicPayController extends BaseFrontController {

    const CMCIC_CTLHMAC = "V1.04.sha1.php--[CtlHmac%s%s]-%s";
    const CMCIC_CTLHMACSTR = "CtlHmac%s%s";
    const CMCIC_CGI2_RECEIPT = "version=2\ncdr=%s";
    const CMCIC_CGI2_MACOK = "0";
    const CMCIC_CGI2_MACNOTOK = "1\n";
    const CMCIC_CGI2_FIELDS = "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*";
    const CMCIC_CGI1_FIELDS = "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s";
    const CMCIC_URLPAIEMENT = "%s/%s";

    protected $_sKey;
    protected $_sUsableKey;

    protected $config;

    public static function harmonise($value, $type, $len)
    {
        switch ($type) {
            case 'numeric':
                $value = (string) $value;
                if(mb_strlen($value, 'utf8') > $len);
                $value = substr($value, 0, $len);
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value = '0' . $value;
                }
                break;
            case 'alphanumeric':
                $value = (string) $value;
                if(mb_strlen($value, 'utf8') > $len);
                $value = substr($value, 0, $len);
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value .= ' ';
                }
                break;
        }

        return $value;
    }

    public function gotopage($order) {
        $ord = OrderQuery::create()->findPk($order);
        if($ord->getCustomerId() != $this->getSession()->getCustomerUser()->getId() ||
            $ord->getOrderStatus()->getCode() != CmCIC::ORDER_NOT_PAID)
            $ord = null;
        if($ord !== null) {
            $c = Config::read(CmCIC::JSON_CONFIG_PATH);
            $currency = $ord->getCurrency()->getCode();
            $opts="";
            $vars = array(
                "url_bank"=> sprintf(self::CMCIC_URLPAIEMENT, $c["CMCIC_SERVER"], $c["CMCIC_PAGE"]),
                "version"=>$c["CMCIC_VERSION"],
                "TPE"=>$c["CMCIC_TPE"],
                "date"=>date("d/m/Y:H:i:s"),
                "montant"=>(string)$ord->getTotalAmount().$currency,
                "reference"=>self::harmonise($ord->getId(),'numeric',12),
                "url_retour"=>URL::getInstance()->absoluteUrl($this->getRouteFromRouter("router.cmcic", "cmcic.receive"))."/".(string)$ord->getId(),
                "url_retour_ok"=>URL::getInstance()->absoluteUrl($this->getRoute("order.placed",array("order_id"=>(string)$ord->getId()))),
                "url_retour_err"=>URL::getInstance()->absoluteUrl($this->getRouteFromRouter("router.cmcic", "cmcic.payfail",array("order_id"=>(string)$ord->getId()))),
                "lgue"=>strtoupper($this->getSession()->getLang()->getCode()),
                "societe"=>$c["CMCIC_CODESOCIETE"],
                "texte-libre"=>"0",
                "mail"=>$this->getSession()->getCustomerUser()->getEmail(),
                "nbrech"=>"",
                "dateech1"=>"",
                "montantech1"=>"",
                "dateech2"=>"",
                "montantech2"=>"",
                "dateech3"=>"",
                "montantech3"=>"",
                "dateech4"=>"",
                "montantech4"=>""
            );
            $hashable=sprintf(
                self::CMCIC_CGI1_FIELDS,
                $vars["TPE"],
                $vars["date"],
                $vars["montant"],
                $vars["reference"],
                $vars["texte-libre"],
                $vars["version"],
                $vars["lgue"],
                $vars["societe"],
                $vars["mail"],
                $vars["nbrech"],
                $vars["dateech1"],
                $vars["montantech1"],
                $vars["dateech2"],
                $vars["montantech2"],
                $vars["dateech3"],
                $vars["montantech3"],
                $vars["dateech4"],
                $vars["montantech4"],
                $opts
            );
            $mac = self::computeHmac(
                $hashable,
                self::getUsableKey($c["CMCIC_KEY"])
            );
            $vars["MAC"] = $mac;
            return $this->render("gotobankservice",$vars);
        } else {
            throw new \Exception(Translator::getInstance()->trans("You shouldn't be here."));
        }

    }

    public static  function getUsableKey($key) {

        $hexStrKey  = substr($key, 0, 38);
        $hexFinal   = "" . substr($key, 38, 2) . "00";

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

    public static function computeHmac($sData, $key) {
        return strtolower(hash_hmac("sha1", $sData, $key));
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

