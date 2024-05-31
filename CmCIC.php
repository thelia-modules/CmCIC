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
use Exception;
use JsonException;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ModuleImageQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class CmCIC extends AbstractPaymentModule
{
    public const DOMAIN_NAME = "cmcic";

    public const JSON_CONFIG_PATH = "/Config/config.json";

    public const CMCIC_CGI2_RECEIPT = "version=2\ncdr=%s";
    public const CMCIC_CGI2_MACOK = "0";
    public const CMCIC_CGI2_MACNOTOK = "1\n";

    /**
     *
     * This method is call on Payment loop.
     *
     * If you return true, the payment method will de display
     * If you return false, the payment method will not be displayed
     *
     * @return boolean
     */
    public function isValidPayment(): bool
    {
        $debug = self::getConfigValue('debug', false);

        if ($debug) {
            // Check allowed IPs when in test mode.
            $testAllowedIps = self::getConfigValue('allowed_ips', '');

            $raw_ips = explode("\n", $testAllowedIps);

            $allowed_client_ips = array();

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = in_array($client_ip, $allowed_client_ips, true);
        } else {
            $valid = true;
        }

        if ($this->getCurrentOrderTotalAmount() <= 0) {
            $valid = false;
        }

        return $valid;
    }

    /**
     * @param ConnectionInterface|null $con
     * @throws PropelException
     * @throws Exception
     */
    public function postActivation(ConnectionInterface $con = null): void
    {
        /* insert the images from image folder if first module activation */
        $configFile = __DIR__ . self::JSON_CONFIG_PATH;
        $configDistFile = __DIR__ . self::JSON_CONFIG_PATH . '.dist';

        if (!file_exists($configFile) && !copy($configDistFile, $configFile)) {
            throw new RuntimeException(
                Translator::getInstance()->trans(
                    "Can't create file %file%. Please change the rights on the file and/or directory."
                )
            );
        }

        $module = $this->getModuleModel();

        if (ModuleImageQuery::create()->filterByModule($module)->count() === 0) {
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

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        // Delete obsolete admin includes
        $fs = new Filesystem();

        try {
            $fs->remove(__DIR__ . '/AdminIncludes');
            $fs->remove(__DIR__ . 'I18n/AdminIncludes');
        } catch (Exception $ex) {
            Tlog::getInstance()->addWarning("Failed to delete CmCIC module AdminIncludes directory (" . __DIR__ . '/AdminIncludes): ' . $ex->getMessage());
        }
    }

    /**
     * @param Order $order
     * @return Response|null
     * @throws Exception
     */
    public function pay(Order $order): ?Response
    {
        $c = Config::read(self::JSON_CONFIG_PATH);
        $currency = $order->getCurrency()->getCode();

        $vars = array(
            "version" => $c["CMCIC_VERSION"],
            "TPE" => $c["CMCIC_TPE"],
            "date" => date("d/m/Y:H:i:s"),
            "montant" => round($order->getTotalAmount(), 2) . $currency,
            "reference" => $this->harmonise($order->getId(), 'numeric', 12),
            "url_retour_ok" => URL::getInstance()->absoluteUrl("/order/placed/".$order->getId()),
            "url_retour_err" => URL::getInstance()->absoluteUrl("/cmcic/payfail/" . $order->getId()),
            "lgue" => strtoupper($this->getRequest()->getSession()?->getLang()->getCode()),
            "contexte_commande" => self::getCommandContext($order),
            "societe" => $c["CMCIC_CODESOCIETE"],
            "texte-libre" => "0",
            "mail" => $this->getRequest()->getSession()?->getCustomerUser()?->getEmail(),
            "3dsdebrayable" => "0",
            "ThreeDSecureChallenge" => "challenge_preferred",
        );

        $hashable = self::getHashable($vars);

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

    protected function harmonise($value, $type, $len): string
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

    public static function getUsableKey($key): string
    {
        $hexStrKey = substr($key, 0, 38);
        $hexFinal = substr($key, 38, 2) . "00";

        $cca0 = ord($hexFinal);

        if ($cca0 > 70 && $cca0 < 97) {
            $hexStrKey .= chr($cca0 - 23) . $hexFinal[1];
        } elseif ($hexFinal[1] === "M") {
            $hexStrKey .= $hexFinal[0] . "0";
        } else {
            $hexStrKey .= substr($hexFinal, 0, 2);
        }

        return pack("H*", $hexStrKey);
    }

    public static function computeHmac($sData, $key): string
    {
        return strtolower(hash_hmac("sha1", $sData, $key));
    }

    /**
     * @param Order $order
     * @return string
     * @throws PropelException
     * @throws JsonException
     */
    public static function getCommandContext(Order $order): string
    {

        $orderAddressId = $order->getInvoiceOrderAddressId();
        $orderAddress = OrderAddressQuery::create()->findPk($orderAddressId);
        $billing = self::orderAddressForCbPayment($orderAddress);


        $deliveryAddressId = $order->getDeliveryOrderAddressId();
        $deliveryAddress = OrderAddressQuery::create()->findPk($deliveryAddressId);
        $shipping = self::orderAddressForCbPayment($deliveryAddress);

        $commandContext = array("billing" => $billing,
            "shipping" => $shipping);

        $json = json_encode($commandContext, JSON_THROW_ON_ERROR);
        $utf8 = utf8_encode($json);
        return base64_encode($utf8);
    }

    /**
     * @param OrderAddress $orderAddress
     * @return array
     * @throws PropelException
     */
    public static function orderAddressForCbPayment(OrderAddress $orderAddress): array
    {
        $address = array(
            "name" => substr($orderAddress->getFirstname() . " " . $orderAddress->getLastname() . " " . $orderAddress->getCompany(), 0, 45),
            "firstName" => substr($orderAddress->getFirstname(), 0, 45),
            "lastName" => substr($orderAddress->getLastname(), 0, 45),
            "addressLine1" => substr($orderAddress->getAddress1(), 0, 50),
            "city" => substr($orderAddress->getCity(), 0, 50),
            "postalCode" => $orderAddress->getZipcode(),
            "country" => $orderAddress->getCountry()->getIsoalpha2()
        );

        if (!empty($orderAddress->getAddress2())) {
            $address["addressLine2"] = substr($orderAddress->getAddress2(), 0, 50);
        }

        if (!empty($orderAddress->getAddress3())) {
            $address["addressLine3"] = substr($orderAddress->getAddress3(), 0, 50);
        }

        if ($orderAddress->getState() !== null) {
            $address["stateOrProvince"] = $orderAddress->getState()->getIsocode();
        }

        /*
         We should not pass a phone number. This number is essential, but if it is provided, it should match the
        documented regexp : (^[0-9]{2,20}$), which is not always the case, a may prevent payment !!

        if (substr($orderAddress->getPhone(),0,1) == "+") {
            $address["phone"] = $orderAddress->getPhone();
        }

        if (substr($orderAddress->getCellphone(),0,1) == "+") {
            $address["mobilePhone"] = $orderAddress->getCellphone();
        }
        */

        return $address;
    }

    /**
     * Get the new format for seal content, for DSP-2 (cf https://www.monetico-paiement.fr/fr/info/documentations/Monetico_Paiement_documentation_migration_3DSv2_1.0.pdf#%5B%7B%22num%22%3A83%2C%22gen%22%3A0%7D%2C%7B%22name%22%3A%22XYZ%22%7D%2C68%2C716%2C0%5D )
     * @param $vars
     * @return string
     */
    public static function getHashable($vars): string
    {
        // Sort by keys according to ASCII order
        ksort($vars);

        // Formats the values in the following way: Nom_champ=Valeur_champ
        array_walk($vars, static function (&$value, $key) {
            $value = "$key=$value";
        });

        // Make it as a single string with * as separation character
        return implode("*", $vars);
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode() . '\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()) . '/I18n/*'])
            ->autowire()
            ->autoconfigure();
    }
}
