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

namespace CmCIC;

use CmCIC\Model\Config;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Routing\Router;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ModuleImageQuery;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class CmCIC extends AbstractPaymentModule
{
    const DOMAIN_NAME = "cmcic";
    const JSON_CONFIG_PATH = "/Config/config.json";
    
    const CMCIC_CTLHMAC = "V1.04.sha1.php--[CtlHmac%s%s]-%s";
    const CMCIC_CTLHMACSTR = "CtlHmac%s%s";
    const CMCIC_CGI2_RECEIPT = "version=2\ncdr=%s";
    const CMCIC_CGI2_MACOK = "0";
    const CMCIC_CGI2_MACNOTOK = "1\n";
    const CMCIC_CGI2_FIELDS = "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*";
    const CMCIC_CGI1_FIELDS = "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s";
    
    protected $sKey;
    protected $sUsableKey;
    
    protected $config;
    
    /**
     *
     * This method is call on Payment loop.
     *
     * If you return true, the payment method will de display
     * If you return false, the payment method will not be display
     *
     * @return boolean
     */
    public function isValidPayment()
    {
        $debug = $this->getConfigValue('debug', false);
        
        if ($debug) {
            // Check allowed IPs when in test mode.
            $testAllowedIps = $this->getConfigValue('allowed_ips', '');
            
            $raw_ips = explode("\n", $testAllowedIps);
            
            $allowed_client_ips = array();
            
            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }
            
            $client_ip = $this->getRequest()->getClientIp();
            
            $valid = in_array($client_ip, $allowed_client_ips);
        } else {
            $valid = true;
        }
        
        return $valid;
    }
    
    public function postActivation(ConnectionInterface $con = null)
    {
        /* insert the images from image folder if first module activation */
        $module = $this->getModuleModel();
        if (ModuleImageQuery::create()->filterByModule($module)->count() == 0) {
            $this->deployImageFolder($module, sprintf('%s/images', __DIR__), $con);
        }
        
        /* set module title */
        $this->setTitle(
            $module,
            array(
                "en_US" => "Pay by Credit Card",
                "fr_FR" => "Paiement par Carte Bancaire",
            )
        );
    }
    
    public function pay(Order $order)
    {
        $c = Config::read(CmCIC::JSON_CONFIG_PATH);
        $currency = $order->getCurrency()->getCode();
        $opts = "";
        $cmCicRouter = $this->container->get('router.cmcic');
        $mainRouter = $this->container->get('router.front');
        
        $vars = array(
            "version" => $c["CMCIC_VERSION"],
            "TPE" => $c["CMCIC_TPE"],
            "date" => date("d/m/Y:H:i:s"),
            "montant" => (string)round($order->getTotalAmount(), 2) . $currency,
            "reference" => $this->harmonise($order->getId(), 'numeric', 12),
            "url_retour" => URL::getInstance()->absoluteUrl($cmCicRouter->generate("cmcic.receive", array(), Router::ABSOLUTE_URL)) . "/" . (string)$order->getId(),
            "url_retour_ok" => URL::getInstance()->absoluteUrl($mainRouter->generate("order.placed", array("order_id" => (string)$order->getId()), Router::ABSOLUTE_URL)),
            "url_retour_err" => URL::getInstance()->absoluteUrl($cmCicRouter->generate("cmcic.payfail", array("order_id" => (string)$order->getId()), Router::ABSOLUTE_URL)),
            "lgue" => strtoupper($this->getRequest()->getSession()->getLang()->getCode()),
            "societe" => $c["CMCIC_CODESOCIETE"],
            "texte-libre" => "0",
            "mail" => $this->getRequest()->getSession()->getCustomerUser()->getEmail(),
            "nbrech" => "",
            "dateech1" => "",
            "montantech1" => "",
            "dateech2" => "",
            "montantech2" => "",
            "dateech3" => "",
            "montantech3" => "",
            "dateech4" => "",
            "montantech4" => ""
        );
        $hashable = sprintf(
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
        
        return $this->generateGatewayFormResponse(
            $order,
            $c["CMCIC_SERVER"] . $c["CMCIC_PAGE"],
            $vars
        );
    }
    
    protected function harmonise($value, $type, $len)
    {
        switch ($type) {
            case 'numeric':
                $value = (string)$value;
                if (mb_strlen($value, 'utf8') > $len) {
                    $value = substr($value, 0, $len);
                }
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value = '0' . $value;
                }
                break;
            case 'alphanumeric':
                $value = (string)$value;
                if (mb_strlen($value, 'utf8') > $len) {
                    $value = substr($value, 0, $len);
                }
                for ($i = mb_strlen($value, 'utf8'); $i < $len; $i++) {
                    $value .= ' ';
                }
                break;
        }
        
        return $value;
    }
    
    public static function getUsableKey($key)
    {
        $hexStrKey = substr($key, 0, 38);
        $hexFinal = "" . substr($key, 38, 2) . "00";
        
        $cca0 = ord($hexFinal);
        
        if ($cca0 > 70 && $cca0 < 97) {
            $hexStrKey .= chr($cca0 - 23) . substr($hexFinal, 1, 1);
        } else {
            if (substr($hexFinal, 1, 1) == "M") {
                $hexStrKey .= substr($hexFinal, 0, 1) . "0";
            } else {
                $hexStrKey .= substr($hexFinal, 0, 2);
            }
        }
        
        return pack("H*", $hexStrKey);
    }
    
    public static function computeHmac($sData, $key)
    {
        return strtolower(hash_hmac("sha1", $sData, $key));
    }
}
